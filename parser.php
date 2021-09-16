<?php

include './api_connect.php';

use App\Connect;

$filename = 'TrueCategory.csv';

class Parser
{
    private $inquiries_today = 0;
    private $count_inquiries_stop = 2000;
    private $count_inquiries_start = 0;
    private $count_items = 0;
    private $log_queue_filename = 'system_info/queue.log';
    private $log_category_filename = 'log_category.log';
    private $log_ready_filename = 'log_ready.csv';
    private $log_inquiries_filename = 'system_info/log_inquiries.log';
    private $restartQueue;
    private $restartCategory;
    private $start = 0;
    private $end = 0;
    private $count_category = 0;
    private $title = [
        'Категория',
        'Неделя',
        'Продаж',
        'Выручка',
        'Товаров',
        'Товаров с продажами',
        'Брендов',
        'Брендов с продажами',
        'Продавцов',
        'Продавцов с продажами',
        'Выручка на товар'
    ];

    public function __construct($restartCategory, $restartQueue)
    {
        $this->restartQueue = $restartQueue;
        $this->restartCategory = $restartCategory;
    }

    private function checkInquiriesToday()
    {
        print_r("Проверка, сколько запросов было за сегодня...\n");
        if (!file_get_contents($this->log_inquiries_filename)) {
            file_put_contents($this->log_inquiries_filename, '');
            file_put_contents($this->log_inquiries_filename, date('Y.m.d') . "|0/$this->count_inquiries_stop"."\n");
        }
        $change_file = file($this->log_inquiries_filename);
        $explode_info = explode('|', $change_file[count($change_file) - 1]);
        $date = $explode_info[0];
        if ($date !== date('Y.m.d')) {
            file_put_contents($this->log_inquiries_filename, date('Y.m.d') . "|0/$this->count_inquiries_stop"."\n", FILE_APPEND);
            $change_file = file($this->log_inquiries_filename);
            $explode_info = explode('|', $change_file[count($change_file) - 1]);
            $date = $explode_info[0];
        }else{
            $count_inquiries = $explode_info[1];
            $this->count_inquiries_start = explode('/', $count_inquiries)[0];
        }
        print_r("Запросов за сегодня [$date]: $this->count_inquiries_start/$this->count_inquiries_stop\n");
        if ($this->count_inquiries_start >= $this->count_inquiries_stop) {
            exit("\nПарсер остановлен, так как выполнено $this->count_inquiries_start/$this->count_inquiries_stop запросов\n");
        }
    }

    private function changeInquiriesToday()
    {
        $change_file = file($this->log_inquiries_filename);
        $explode_info = explode('|', $change_file[count($change_file) - 1]);
        $date = $explode_info[0];
        $count_inquiries = $explode_info[1];
        $this->count_inquiries_start = explode('/', $count_inquiries)[0];
        $result = '';
        foreach ($change_file as $line) {
            if ($line ==
                ($date . "|" . $this->count_inquiries_start . "/" . $this->count_inquiries_stop . "\n")) {
                $result .= ($date . "|" . (++$this->count_inquiries_start) . "/" . $this->count_inquiries_stop) . "\n";
            } else {
                $result .= $line;
            }
        }
        if ($this->count_inquiries_start >= $this->count_inquiries_stop) {
            exit("\nПарсер остановлен, так как выполнено $this->count_inquiries_start/$this->count_inquiries_stop запросов\n");
        }
        file_put_contents($this->log_inquiries_filename, $result);
    }


    public function start()
    {
        $this->checkInquiriesToday();
        $this->buildQueueWithoutDailyStatistics();
        $this->loggingForCategory();
    }

    public function addInfoForQueue($start, $end)
    {
        file_put_contents($this->log_queue_filename, "$start/$end\n", FILE_APPEND);
        print_r("\rПозиция в очереди : $start/$end");
    }

    private function addTitleForReadyLog($filename)
    {
        print_r('Установка заголовков...' . "\n");
        file_put_contents($filename, '');
        file_put_contents($filename, implode(';', $this->title));
    }

    public function buildQueueWithoutDailyStatistics()
    {
        if (!file_get_contents($this->log_ready_filename)) {
            $this->addTitleForReadyLog($this->log_ready_filename);
        }
        if ($this->restartQueue) {
            $this->addTitleForReadyLog($this->log_ready_filename);
            print_r("Обновление очереди по категориям и файлов с результатами...\n");
            $this->parseCategory();
            file_put_contents($this->log_queue_filename, '');
            print_r("Очередь обнулена...\n");
            file_put_contents($this->log_queue_filename,
                "0/$this->count_category\n",
                FILE_APPEND);
            file_put_contents($this->log_ready_filename, '');
            print_r("Очередь обнулена...\n");
            $this->start = 0;
            $this->end = $this->count_category;
            print_r("\nОчередь китегорий обновлена : 0/$this->count_category\n");
            return 1;
        }
        if (!file_get_contents($this->log_queue_filename)) {
            print_r("Начальное построение очереди...\n");
            $this->parseCategory();
            file_put_contents($this->log_queue_filename, '');
            file_put_contents(
                $this->log_queue_filename,
                "0/$this->count_category\n",
                FILE_APPEND);
            $this->start = 0;
            $this->end = $this->count_category;
            print_r("Очередь установлена : 0/$this->count_category\n");
            return 1;
        } else {
            if ($this->restartCategory) {
                file_put_contents($this->log_category_filename, '');
                print_r("Перезапуск категорий...\n");
                $this->parseCategory();
            }
            print_r("Продолжение очереди...\n");
            $info_queue = file_get_contents($this->log_queue_filename);
            $last_queue = explode("\n", $info_queue);
            $count_queue = explode('/', $last_queue[count($last_queue) - 2]);
            $this->count_category = $count_queue[1];
            $this->start = $count_queue[0];
            $this->end = $count_queue[1];
            print_r("Обработано категорий : $this->start/$this->end\n");
            return 1;
        }
    }

    public function checkQueue()
    {
        if (
            gettype((int)$this->start) !== "integer"
            or
            gettype((int)$this->end) !== "integer"
        ) {
            exit("\nОшибка очередей, отчистите файле queue.log\n");
        } elseif ($this->start > $this->end) {
            exit("\nОчереди были неправильно определены, отчистите файл queue.log\n");
        }
    }

    public function loggingForCategory()
    {
        print_r("\nЗапуск запросов информации по категириям...\n");

        $info_categories = file_get_contents($this->log_category_filename);
        $rows_categories = explode("\n", $info_categories);
        $this->checkQueue();

        $count_string = 0;
        for ($item = $this->start; $item <= $this->end; $item++) {
            $this->changeInquiriesToday();
            if (!isset($rows_categories[$item])) {
                exit("\nКатегория не определена, парсинг остановлен\n");
            }
            $request = new Connect('get/category/trends', '2021-07-05', $rows_categories[$item], "GET");
            $result = json_decode($request->getInfoForApi());
            if ($result) {
                foreach ($result as $info_category) {
                    file_put_contents($this->log_ready_filename,
                        str_replace(
                            ["\r\n", "\r", "\n"],
                            "",
                            urldecode($rows_categories[$item]) . ";"
                            . $info_category->week . ";"
                            . $info_category->sales . ";"
                            . $info_category->revenue . ";"
                            . $info_category->items . ";"
                            . $info_category->items_with_sells . ";"
                            . $info_category->brands . ";"
                            . $info_category->brands_with_sells . ";"
                            . $info_category->sellers . ";"
                            . $info_category->sellers_with_sells . ";"
                            . $info_category->product_revenue)
                        . PHP_EOL, FILE_APPEND);
                }
            }
            $this->addInfoForQueue($item + 1, $this->end);
            $result = null;
        }
        print_r("\n--------------------\nОБРАБОТКА ЗАВЕРШЕНА\n--------------------\n");
    }

    public function parseCategory()
    {
        print_r("\nПоиск категорий...\n");
        file_put_contents($this->log_category_filename, '');
        if (file_exists($this->log_category_filename) and is_readable($this->log_category_filename)) {
            $request = new Connect('get/categories', '', '', "GET");
            $result = json_decode($request->getInfoForApi());
            foreach ($result as $item) {
                if ($item->path !== "") {
                    $this->count_category += 1;
                    file_put_contents($this->log_category_filename, urlencode($item->path) . "\n", FILE_APPEND);
                }
            }
        }
        print_r("\nКатегорий найдено: $this->count_category\n");
    }
}

// Первое значение - (true/false) перезапускать ли поиск категорий
// Второе значение - (true/false) перезапускать ли очередь
$parser = new Parser(false, false);
$parser->start();