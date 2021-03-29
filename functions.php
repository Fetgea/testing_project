<?php
/**
 * Function connecting to database with hardcoded parameters
 * @param bool $connectToDb if true will use $db_name param from config in mysqli_connect
 * @return mixed connection object if connection sucessfull or false if not
 */

function connect(bool $connectToDb = true)
{
    $ini = parse_ini_file("config.ini");
    $configErrors = [];
    if (!extension_loaded("mysqli")) {
        return ["error" => "php_mysqli extension not installed"];
    }
    if (!$ini) {
        return ["error" => "Config file I/O error"];
    }
    $configRequired = ["db_name", "db_host", "db_user", "db_password"];

    foreach ($configRequired as $configValue) {
        if (!$connectToDb && $configValue == "db_name") {
            continue;
        }
        if (!isset($ini[$configValue])) {
            $configErrors["error"][$configValue] = "Отсутствует параметр " . $configValue;
            continue;
        }
        $ini[$configValue] = trim($ini[$configValue]);
        if (empty($ini[$configValue])) {
            $configErrors["error"][$configValue] = $configValue . " пустой";
        }
    }
    if (!empty($configErrors)) {
        return $configErrors;
    }
    if ($connectToDb) {
        $connection = @mysqli_connect($ini["db_host"], $ini["db_user"], $ini["db_password"], $ini["db_name"]); //так вообще стоит делать?
    } else {
        $connection = @mysqli_connect($ini["db_host"], $ini["db_user"], $ini["db_password"]);
    }
    if (mysqli_connect_error() != NULL) {
        return ["error" => "DB Connection error: " . mysqli_connect_error()];
    }
    return $connection;
}
/**
 * Function returns all information about book by its ID
 * @param int $id ID of requested book 
 * @return mixed array(ID, Title, Author Publication_date, number_of_pages) of requested book,
 * or error text in case of error
 */

function getById(int $id)
{
    $connection = connect();
    if (!is_object($connection) && isset($connection["error"])) {
        return $connection;
    }
    $query = "SELECT id, title, author, publication_date, number_pages FROM book WHERE id = ?";
    if ($preparedQuery = mysqli_prepare($connection, $query)) {
        mysqli_stmt_bind_param($preparedQuery, "i", $id);
        if (mysqli_stmt_execute($preparedQuery)) {
            $result = mysqli_stmt_get_result($preparedQuery);
            $arResult = mysqli_fetch_assoc($result);
            if (!empty($arResult)) {
                return $arResult;
            } else {
                mysqli_close($connection);
                return ["error" => "No book with provided ID in DB"];
            }
        }
        mysqli_close($connection);
        return ["error" => "Query Error:" . $preparedQuery->error];
    } 
    $error = mysqli_error($connection);
    mysqli_close($connection);
    return ["error" => "Prepared statement error: " . $error];
}
/**
 * Returns all books information from DB
 * 
 * @return mixed array of all books from DB, or error text in case of error
 */
function getAllBooks()
{
    $connection = connect();
    if (!is_object($connection) && isset($connection["error"])) {
        return $connection;
    }
    $query = "SELECT id, title, author, publication_date, number_pages FROM book";
    $preparedQuery = mysqli_prepare($connection, $query);
    if (!$preparedQuery) {
        return ["error" => mysqli_error($connection)];
    }
    if (mysqli_stmt_execute($preparedQuery)) {
        $resultObj = mysqli_stmt_get_result($preparedQuery);
        while ($result = mysqli_fetch_assoc($resultObj)) {
            $arResult[] = $result;
        }
        if (!empty($arResult)) {
            mysqli_close($connection);
            return $arResult;
        }
        mysqli_close($connection);
        return ["error" => "No books in db"];
    }
}
/**
 * Inserts new book into db, makes some validation of input before inserting
 * 
 * @param string $title Title of the new book
 * @param string $author Author of the book
 * @param int $publicationDate Timestamp of date of publication
 * @param int $numberPages Number of pages
 * @return  mixed Returns true for successfull db insert query, in case of error returns error text
 */

function createNewBook(string $title, string $author, int $publicationDate, int $numberPages)
{
    $arArguments = [
        "title" => $title,
        "author" => $author,
        "publication_date" => $publicationDate,
        "number_of_pages" => $numberPages
    ];
    foreach ($arArguments as $key => &$argument) {
        $argument = htmlspecialchars($argument);
        $argument = trim($argument);
        if (empty($argument)) {
            return ["error" => "Empty argument: " . $key];
        }
    }

    try {
        $date = new DateTime();
        if (!$date->setTimestamp($arArguments["publication_date"])) {
            return ["error" => "Error setting date"];
        }
        $dateFormatted = $date->format("Y-m-d");
    } catch (\Exception $e) {
        return ["error" => "Error text" . $e];
    }
    $connection = connect();
    if (!is_object($connection) && isset($connection["error"])) {
        return $connection;
    }
    $query = "INSERT INTO book (title, author, publication_date, number_pages) VALUES (?, ?, ?, ?)";
    if ($preparedQuery = mysqli_prepare($connection, $query)) {
        mysqli_stmt_bind_param($preparedQuery, "sssi", $arArguments["title"], $arArguments["author"], $dateFormatted, $arArguments["number_of_pages"]);
        if (mysqli_stmt_execute($preparedQuery)) {
            mysqli_close($connection);
            return true;
        }
        mysqli_close($connection);
        return ["error" => "STMT query error: " . mysqli_stmt_error($preparedQuery)];
    }
    return ["error" => "STMT query error: " . mysqli_error($connection)];
}
/**
 * Creates database and populates it with 3 dummy records. You can chec file createDB.sql for sql commands for this proccess
 *
 * @return mixed true if no problems in process of creating and populating DB, array of errors if there is error in process
 */
function populateDatabase()
{
    $connection = connect(false);
    if (!is_object($connection) && isset($connection["error"])) {
        return $connection;
    }
    $sqlQueries = file_get_contents("./createDB.sql");
    if (!$sqlQueries) {
        return ["error" => "Cannot open file with sql queries"];
    }
    $errors = [];
    if (mysqli_multi_query($connection, $sqlQueries)) {
        if ($error = mysqli_error($connection)) {
            $errors[] = $error;
        }
        while (mysqli_next_result($connection)) {
            if ($error = mysqli_error($connection)) {
                $errors[] = $error;
            }
            if (mysqli_more_results($connection)) {
                break;
            }
        }
        if (!empty($errors)) {
            return ["error" => $errors];
        }
        return true;
    }
    return ["error" => mysqli_error($connection)];
}