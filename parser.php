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
    private $log_category_urlencode_filename = 'log_category_urlencode.log';
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
            file_put_contents($this->log_inquiries_filename, date('Y.m.d') . "|0/$this->count_inquiries_stop" . "\n");
        }
        $change_file = file($this->log_inquiries_filename);
        $explode_info = explode('|', $change_file[count($change_file) - 1]);
        $date = $explode_info[0];
        if ($date !== date('Y.m.d')) {
            file_put_contents($this->log_inquiries_filename, date('Y.m.d') . "|0/$this->count_inquiries_stop" . "\n", FILE_APPEND);
            $change_file = file($this->log_inquiries_filename);
            $explode_info = explode('|', $change_file[count($change_file) - 1]);
            $date = $explode_info[0];
        } else {
            $count_inquiries = $explode_info[1];
            $this->count_inquiries_start = explode('/', $count_inquiries)[0];
        }
        print_r("Запросов за сегодня [$date]: $this->count_inquiries_start/$this->count_inquiries_stop\n");
        if ($this->count_inquiries_start > $this->count_inquiries_stop) {
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
        if ((int)$start >= (int)$end) {
            exit("\nВсе запросы были выплнены\n");
        }
    }

    private function addTitleForReadyLog($filename)
    {
        print_r('Установка заголовков...' . "\n");
        file_put_contents($filename, '');
        file_put_contents($filename, (implode(';', $this->title)."\n"));
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
                file_put_contents($this->log_category_urlencode_filename, '');
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
            if ((int)$this->start >= (int)$this->end) {
                exit("\nВсе запросы были выплнены [$this->start / $this->end]\n");
            }
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
            exit("\nОшибка очередей, проверьте файл queue.log\n");
        } elseif ($this->start > $this->end) {
            exit("\nОчереди были неправильно определены, проверьте файл queue.log\n");
        }
    }

    public function loggingForCategory()
    {
        print_r("\nЗапуск запросов информации по категириям...\n");
        $info_categories = file_get_contents($this->log_category_urlencode_filename);
        $rows_categories = explode("\n", $info_categories);
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
            $this->checkQueue();
            $result = null;
        }
        print_r("\n--------------------\nОБРАБОТКА ЗАВЕРШЕНА\n--------------------\n");
    }


    private function findToLogCategory($category)
    {
        $file = explode("\n", file_get_contents($this->log_category_filename));
        foreach ($file as $line) {
            if ($line === $category) {
                return false;
            }
        }
        return true;
    }

    private function sendInLogCategory($string)
    {
        if ($this->findToLogCategory($string)) {
            file_put_contents($this->log_category_filename, $string . "\n", FILE_APPEND);
            $this->count_category += 1;
        }
    }

    private function sendInEncodeLogCategory($string)
    {
        file_put_contents($this->log_category_urlencode_filename, urlencode($string) . "\n", FILE_APPEND);
    }

    private function parserItem($string)
    {
        $ready_massive = [];
        $result = explode('/', $string);

        foreach ($result as $result_key => $result_item) {
            if ($result_key === 0) {
                array_push($ready_massive, $result_item);
                continue;
            }
            array_push($ready_massive, ($ready_massive[$result_key - 1] . '/' . $result_item));
        }
        foreach ($ready_massive as $sting_to_send) {
            $this->sendInLogCategory($sting_to_send);
        }
    }


    public function parseCategory()
    {
        print_r("\nПоиск категорий...\n");
        file_put_contents($this->log_category_filename, '');
        file_put_contents($this->log_category_urlencode_filename, '');
        if (file_exists($this->log_category_filename) and is_readable($this->log_category_filename)) {
            $request = new Connect('get/categories', '', '', "GET");
            $result = json_decode($request->getInfoForApi());
            foreach ($result as $item) {
                if ($item->path !== "") {
                    $this->parserItem($item->path);
                }
            }
            print_r("\n[log_category.log] Категории, удобные для человеческого глаза готовы\n");
            $items = explode("\n", file_get_contents($this->log_category_filename));
            foreach ($items as $sting_to_send) {
                $this->sendInEncodeLogCategory($sting_to_send);
            }
            print_r("\n[log_category_urlencode.log] Категории готовы к парсингу\n");

        }
        print_r("\nКатегорий найдено: $this->count_category\n");
    }
}

// Первое значение - (true/false) перезапускать ли поиск категорий
// Второе значение - (true/false) перезапускать ли очередь
$parser = new Parser(false, false);
$parser->start();