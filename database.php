<?php

class Database
{
    /**
    * Thông số kết nối dạng localhost
    * @param string $host | Tên host
    * @param string $user | Tên người dùng
    * @param string $pass | Password người dùng
    * @param string $db | Tên database
    */
    protected $host = '';
    protected $user = '';
    protected $pass = '';
    protected $db = '';
    /**
	* Thông số kết nối dạng web
	protected $host = 'mysql.hostinger.vn';
    protected $user = '';
    protected $pass = '';
    protected $db = '';
    */
    private $conn = NULL;
    private $result = NULL;

    public function getConn()
    {
        if (isset($this->conn)) {
            return $this->conn;
        }
    }

    // public function getAppKey()
    // {
    //     $this->connect();
    //     $sql = "SELECT * FROM config ORDER BY updated_at, created_at DESC";
    //     $this->query($sql);
    //     $key = $this->first();
    //     $this->close();

    //     return $key;
    // }

    // public function getKeyMode($appKey = '')
    // {
    //     $this->connect();
    //     $sql = "SELECT mode FROM config WHERE app_key = '$appKey'";
    //     $this->query($sql);
    //     $mode = $this->first();
    //     $this->close();

    //     return $mode;
    // }

    public function connect()
    {
        $this->conn = mysqli_connect($this->host, $this->user, $this->pass) or die('Không thể kết nối tới cơ sở dữ liệu!');
        mysqli_select_db($this->conn, $this->db) or die('Không tìm thấy cơ sở dữ liệu');
        mysqli_query($this->conn, 'SET NAMES utf8'); //or mysqli_set_charset();
    }

    public function close()
    {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }

    public function query($cmd)
    {
        $this->result = mysqli_query($this->conn, $cmd) or die('Câu lệnh: ' . $cmd . ' lỗi: ' . mysqli_error($this->getConn()));
    }

    public function numRows()
    {
        if ($this->result) {
            $row = mysqli_num_rows($this->result);
        } else {
            $row = 0;
        }
        return $row;
    }

    public function affectedRows()
    {
        if ($this->result) {
            $row = mysqli_affected_rows($this->getConn());
        } else {
            $row = 0;
        }
        return $row;
    }

    public function fetch()
    {
        if ($this->result) {
            $data = mysqli_fetch_assoc($this->result);
        } else {
            $data = NULL;
        }
        return $data;
    }

    /**
	* Fetch dữ liệu dạng mảng theo 2 cách: MYSQLI_NUM hoặc MYSQLI_ASSOC
	* @param string $option | NUM: fetch dạng numeric, ASSOC: fetch dạng associate
	* @return array | Dòng dữ liệu dạng mảng
    */
    public function toArray($option)
    {
        if ($this->result) {
            switch ($option) {
                case 'NUM':
                    $row = mysqli_fetch_array($this->result, MYSQLI_NUM);
                    break;
                case 'ASSOC':
                    $row = mysqli_fetch_array($this->result, MYSQLI_ASSOC);
                    break;
            }
            return $row;
        }
    }

    /**
	* Lấy dữ liệu tại dòng đầu tiên, cột đầu tiên
	* @return obj | any type data
    */
    public function first()
    {
        if ($this->result) {
            $first = mysqli_fetch_array($this->result, MYSQLI_NUM);
            $data = $first[0];
        }
        return $data;
    }

    public function escapeHtml($arr)
    {
        $result = array_map('htmlspecialchars', $arr);
        return $result;
    }

    public function refValues($arr)
    {
        if (strnatcmp(phpversion(), '5.3') >= 0) //Reference is required for PHP 5.3+
        {
            $refs = array();
            foreach($arr as $key => $value)
                $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }

    public function setParamType($param)
    {
        $type = gettype($param);
        switch($type) {
            case 'integer':
                $pattern = 'i';
                break;
            case 'double':
                $pattern = 'd';
                break;
            case 'string':
                $pattern = 's';
                break;
            case 'blob':
                $pattern = 'b';
                break;
            default:
                $pattern = 's';
        }
        return $pattern;
    }

    /**
	* CRUD dạng active record có sử dụng parameter chống SQL Injection
	* insert($table, $data)
	* VD: insert('sv', ['id' => 1, 'Hoten' => 'trunglecntt']);
	* <=> "INSERT INTO sv(id, Hoten) VALUES('1', 'trunglecntt')"
    * ----
    * update($table, $data, $identifier)
    * VD: update('sv, ['Hoten' => 'aaa'], ['id' => 1]);
    * <=> "UPDATE sv SET Hoten = 'aaa' WHERE id = 1"
    * ----
    * delete($table, $identifier)
    * VD: delete('sv', ['id' => 1]);
    * <=> "DELETE FROM sv WHERE id = 1"
    */
    public function insert($table, $data)
    {
        $this->connect();
        $conn = $this->getConn();

        $data = $this->escapeHtml($data);
        $table = mysqli_real_escape_string($this->getConn(), $table);
        $values = $columns = '';
        foreach($data as $key => $value) {
            $columns .= "$key, ";
            $values .= "?, ";
        }
        $columns = substr($columns, 0, strlen($columns) - 2);
        $values = substr($values, 0, strlen($values) - 2);
        $sql = '
            INSERT INTO ' . $table . '(' . $columns .')
            VALUES(' . $values . ')
        ';

        $types = '';
        foreach($data as $value) {
            $types .= $this->setParamType($value);
        }

        $terms = array_merge(array($types), $data);
        $stmt = $conn->prepare($sql);
        call_user_func_array(array($stmt, 'bind_param'), $this->refValues($terms));

        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $status = true;
        } else {
            $status = false;
        }
        $this->close();

        return $status;
    }

    public function update($table, $data, $identifier)
    {
        $this->connect();
        $conn = $this->getConn();

        $data = $this->escapeHtml($data);
        $table = mysqli_real_escape_string($this->getConn(), $table);
        foreach ($identifier as $key => $value) {
            $idName = $key;
            $idData = mysqli_real_escape_string($this->getConn(), $value);
        }

        $values = '';
        foreach($data as $key => $value) {
            $values .= "$key = ?, ";
        }

        $values = substr($values, 0, strlen($values) - 2);
        $sql = "
            UPDATE $table
            SET $values
            WHERE $idName = '$idData'
        ";

        $types = '';
        foreach($data as $value) {
            $types .= $this->setParamType($value);
        }

        $terms = array_merge(array($types), $data);
        $stmt = $conn->prepare($sql);
        call_user_func_array(array($stmt, 'bind_param'), $this->refValues($terms));
        $stmt->execute();

        if (! $stmt->error) {
            $status = true;
        } else {
            $status = false;
        }
        $this->close();

        return $status;
    }

    public function delete($table, $identifier)
    {
        $this->connect();
        $conn = $this->getConn();

        $table = mysqli_real_escape_string($this->getConn(), $table);
        foreach ($identifier as $key => $value) {
            $idName = $key;
            $idData = mysqli_real_escape_string($this->getConn(), $value);
        }

        $sql = "
            DELETE FROM $table
            WHERE $idName = ?
        ";
        $type = $this->setParamType($idData);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($type, $param);
        $param = $idData;
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $status = true;
        } else {
            $status = false;
        }
        $this->close();

        return $status;
    }

    //Get all records in table and order by specified column
    public function getAll($table, $order)
    {
        $this->connect();
        $this->table = $table;

        $sql = 'SELECT * FROM ' . $table . ' ORDER BY ' . $order;
        $this->query($sql);

        $data = array();
        while ($row = $this->result->fetch_assoc()) {
            $data[] = $row;
        }
        $this->close();

        return $data;
    }

    //Get one record in table
    public function getOne($table, $identifier)
    {
        $this->connect();

        foreach ($identifier as $key => $value) {
            $idName = $key;
            $idData = mysqli_real_escape_string($this->getConn(), $value);
        }

        $sql = "SELECT * FROM $table WHERE $idName = '$idData'";
        $this->query($sql);

        $data = $this->result->fetch_assoc();
        $this->close();

        return $data;
    }

    //Get all record with specified condition
    public function getFilter($table, $where)
    {
        $this->connect();
        foreach ($where as $key => $value) {
            $columnName = $key;
            $filterBy = mysqli_real_escape_string($this->getConn(), $value);
        }
        $sql = "SELECT * FROM $table WHERE $columnName = '$filterBy'";
        $this->query($sql);
        $data = array();
        while($row = $this->result->fetch_assoc()) {
            $data[] = $row;
        }
        $this->close();

        return $data;
    }
}
