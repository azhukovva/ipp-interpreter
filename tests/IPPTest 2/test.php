<?php
if (! function_exists('pcntl_fork')) die('PCNTL functions not available on this PHP installation');
//------------------Global Vars----------------------
$skip_arg0 = true;

$test_dir = ".";
$recursive = false;
$parse_script = "parse.py";
$int_script = "interpret.php";
$parse_only = false;
$int_only = false;
$jexampath = "/pub/courses/ipp/jexamxml";
$no_clean = false;
$incompat = "";
$php_path = "/usr/bin/php8.3";
$python_path = "python3.10";
$WIN = false;
//insted of outputing to stdout output to file
$outfile = "";

$pass = "";
$fail = "";

$passed_count = 0;
$error_count = 0;
$test_count=0;
$testNO = 0;
$percent = 0;
$percent_interval = 1;

$thread_count = 1;

//-----------------Suport for WindowsOS----------------------------
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $WIN=true;
}

//-----------------Get and test correct arguments------------------
foreach($argv as $arg)
{
    if($skip_arg0)
    {
        $skip_arg0 = false;
        continue;
    }
    $split_arg = preg_split("(^([^=]+)\K=)",$arg);
    switch($split_arg[0])
    {
        case "--help":
            echo "Usage: php -f test.php -- [options]\n";
            echo "\n";
            echo "  --help                 this help\n";
            echo "  --directory=<path>     Finds test in selected directory (if not set, in current)\n";
            echo "  --recursive            If set searches for .src files recursively\n";
            echo "  --parse-script=<file>  File with parse script to test (parse.php is implicit)\n";
            echo "  --int-script=<file>    File with interpret script to test (interpret.py is implicit)\n";
            echo "  --parse-only           Tests only parse script (incompatible with --int-only and --int-script)\n";
            echo "  --int-only             Tests only interpret script (incompatible with --parse-script, --parse-only\n";
            echo "                         and --jexampath)\n";
            echo "  --jexampath=<path>     Path to directory with jexamxml.jar for XML comparison\n";
            echo "  --noclean              If set does not clean temporary files\n";
            echo "\n";
            echo "  --php-path=<file>      Php interpret file\n";
            echo "  --python-path=<file>   Python interpret file\n";
            echo "  --output=<file>        Output file for HTML (stdout is implicit)\n";
			echo "  --threads=<#Threads>   In default this program runs in one thread if you want faster testing use more threads negative numbers use (# of Tests / 200) threads\n";
            echo "\n";
            exit(0);
        case "--output":
            if(count($split_arg)!=1 && $split_arg[1]){
                $outfile = $split_arg[1];
            }else
            {
                fwrite(STDERR, "Unknown parameter $split_arg[0]=$split_arg[1]\n");
                exit(41);
            }
            break;
        case "--php-path":
            if(count($split_arg)!=1 && $split_arg[1]){
                //echo "test";
                $php_path = $split_arg[1];

                if(!file_exists($php_path))
                {
                    fwrite(STDERR, "File does not exist $split_arg[0]=$split_arg[1]\n");
                    exit(41);
                }

            }else
            {
                fwrite(STDERR, "Missing part of argument use --php-path=\"file\"\n");
                exit(41);
            }
            break;

        case "--python-path":
            if(count($split_arg)!=1 && $split_arg[1]){
                //echo "test";
                $python_path = $split_arg[1];

                if(!file_exists($python_path))
                {
                    fwrite(STDERR, "File does not exist $split_arg[0]=$split_arg[1]\n");
                    exit(41);
                }

            }else
            {
                fwrite(STDERR, "Missing part of argument use --python-path=\"file\"\n");
                exit(41);
            }
            break;
        case "--directory":
            if(count($split_arg)!=1 && $split_arg[1]){
                //echo "test";
                $test_dir = $split_arg[1];
                if(!file_exists($test_dir))
                {
                    fwrite(STDERR, "File does not exist $split_arg[0]=$split_arg[1]\n");
                    exit(41);
                }
            }else
            {
                //echo "noif";
                fwrite(STDERR, "Missing part of argument use --directory=\"path\"\n");
                exit(41);
            }
            break;
        case "--recursive":
            if(count($split_arg)!=1 && $split_arg[1]){
                //echo "test";
                fwrite(STDERR, "Unknown parameter $split_arg[0]=$split_arg[1] use only $split_arg[0]\n");
                exit(10);
            }else
            {
                //echo "noif";
                $recursive = true;
                //exit(1);
            }
            break;
        case "--parse-script":
            if(count($split_arg)!=1 && $split_arg[1])
            {
                $incompat_params = preg_split("[;]",$incompat);
                foreach ($incompat_params as $param)
                {
                    if ($param == "int-only") {
                        fwrite(STDERR, "Incompatible parameters\n");
                        exit(10);
                    }
                }
                $parse_script = $split_arg[1];
                $incompat .= "parse-script;";
                if(!file_exists($parse_script))
                {
                    fwrite(STDERR, "File does not exist $split_arg[0]=$split_arg[1]\n");
                    exit(41);
                }
            }else
            {
                fwrite(STDERR, "Missing part of argument use --parse-script=\"file\"\n");
                exit(41);
            }
            break;
        case "--int-script":
            if(count($split_arg)!=1 && $split_arg[1])
            {
                $incompat_params = preg_split("[;]",$incompat);
                foreach ($incompat_params as $param)
                {
                    if ($param == "parse-only") {
                        fwrite(STDERR, "Incompatible parameters\n");
                        exit(10);
                    }
                }
                $int_script = $split_arg[1];
                $incompat .= "int-script;";
                if(!file_exists($int_script))
                {
                    fwrite(STDERR, "File does not exist $split_arg[0]=$split_arg[1]\n");
                    exit(41);
                }
            }else
            {
                fwrite(STDERR, "Missing part of argument use --int-script=\"file\"\n");
                exit(41);
            }
            break;
        case "--parse-only":
            if(count($split_arg)!=1 && $split_arg[1]){
                //echo "test";
                fwrite(STDERR, "Unknown parameter $split_arg[0]=$split_arg[1] use only $split_arg[0]\n");
                exit(10);
            }else
            {
                $incompat_params = preg_split("[;]",$incompat);
                foreach ($incompat_params as $param)
                {
                    switch($param)
                    {
                        case "int-only":
                        case "int-script":
                            fwrite(STDERR, "Incompatible parameters\n");
                            exit(10);
                    }
                }
                $parse_only = true;
                $incompat .= "parse-only;";
            }
            break;
        case "--int-only":
            if(count($split_arg)!=1 && $split_arg[1]){
                fwrite(STDERR, "Unknown parameter $split_arg[0]=$split_arg[1] use only $split_arg[0]\n");
               exit(10);
            }else
            {
                $incompat_params = preg_split("[;]",$incompat);
                foreach ($incompat_params as $param)
                {
                    switch($param)
                    {
                        case "parse-only":
                        case "parse-script":
                        case "jexampath":
                            fwrite(STDERR, "Incompatible parameters\n");
                            exit(10);
                    }
                }
                $int_only = true;
                $incompat .= "int-only;";
            }
            break;
        case "--jexampath":
            if(count($split_arg)!=1 && $split_arg[1])
            {
                $incompat_params = preg_split("[;]",$incompat);
                foreach ($incompat_params as $param)
                {
                    if ($param == "int-only") {
                        fwrite(STDERR, "Incompatible parameters\n");
                        exit(10);
                    }
                }
                $jexampath = $split_arg[1];
                $incompat .= "jexampath;";
                if(!file_exists($jexampath))
                {
                    fwrite(STDERR, "File does not exist $split_arg[0]=$split_arg[1]\n");
                    exit(41);
                }
            }else
            {
                fwrite(STDERR, "Missing part of argument use --jexampath=\"path\"\n");
                exit(41);
            }
            break;
        case "--noclean":
            if(count($split_arg)!=1 && $split_arg[1]){
                //echo "test";
                fwrite(STDERR, "Unknown parameter $split_arg[0]=$split_arg[1] use only $split_arg[0]\n");
                exit(10);
            }else
            {
                //echo "noif";
                $no_clean = true;
                //exit(1);
            }
            break;
        case "--threads":
            if(count($split_arg)!=1 && $split_arg[1])
            {
                $thread_count = intval($split_arg[1]);
            }else
            {
                fwrite(STDERR, "Missing part of argument use --jexampath=\"path\"\n");
                exit(41);
            }
            break;
        default:
            fwrite(STDERR, "Unknown parameter $split_arg[0] skipping.\n");
            //exit(10);
    }
}

/**
 * @param string $dir Directory to search for .src files
 * @param bool $rec Search recursively
 * @param array $result Aray of found filenames without file extension
 * @return array
 */
function get_files_from_dir(string $dir, bool $rec, array &$result): array
{
    //test if dir is R/W
    if(!is_readable($dir)){
        fwrite(STDERR, "DIR not readeble: $dir\n");
        exit(11);
    }

    if(!is_writable($dir)){
        fwrite(STDERR, "DIR not writable: $dir\n");
        exit(12);
    }

    $files = scandir($dir);
    unset($files[0],$files[1]);
    $testname = "";

    foreach ($files as $file)
    {
        $tmp = $dir.DIRECTORY_SEPARATOR.$file;

        if(preg_match("(\.src$)",$tmp))
        {
            if(!is_readable($tmp)){
                fwrite(STDERR, "File not readeble: $tmp\n");
                exit(11);
            }

            if($testname != preg_split("(\.src$)",$tmp))
            {
                $testname = preg_split("(\.src$)",$tmp);
                $result[] = $testname[0];
            }
            $base_file = substr($tmp,0,-4);
            if(!file_exists("$base_file.in"))
            {
                file_put_contents("$base_file.in","");
            }else
            {
                if(!is_readable($tmp)){
                    fwrite(STDERR, "File not readeble: $base_file.in\n");
                    exit(11);
                }
            }
            if(!file_exists("$base_file.rc"))
            {
                file_put_contents("$base_file.rc","0");
            }else
            {
                if(!is_readable($tmp)){
                    fwrite(STDERR, "File not readeble: $base_file.rc\n");
                    exit(11);
                }
            }
            if(!file_exists("$base_file.out")) {
                file_put_contents("$base_file.out", "");
            }else
            {
                if(!is_readable($tmp)){
                    fwrite(STDERR, "File not readeble: $base_file.out\n");
                    exit(11);
                }
            }
        }
        if(is_dir($dir.DIRECTORY_SEPARATOR.$file)&&$rec)
        {
            get_files_from_dir($dir . DIRECTORY_SEPARATOR . $file, $rec,$result) ;
        }

    }

    if ($result == null)
    {
        exit(0);
    }
    return $result;
}

/**
 * @param int $i Number of indents for by folder view
 * @return string HTML string with the indent amount
 */
function structure(int $i): string
{
    $str = " ⊢";
    if($i>0)
    {
        for($j=1;$j<$i;$j++)
        {
            $str = "  ".$str;
        }
        return $str;
    }
    return "";
}

/**
 * @param string $file1
 * @param string $file2
 * @param string $exe Name of program to compare the files (WIN, UNIX, JEXAM)
 * @return bool
 */
function files_are_identical(string $file1, string $file2, string $exe): bool
{
    switch($exe)
    {
        case "WIN":
            exec("FC \"$file1\" \"$file2\"", $out, $rc);
            if($rc == 0)
                return true;
            else
                return false;
        case "UNIX":
            exec("diff $file1 $file2", $out, $rc);
            if($rc == 0)
                return true;
            else
                return false;
        case "JEXAM":
            global $jexampath,$result;
            if(exec("java -jar \"$jexampath".DIRECTORY_SEPARATOR."jexamxml.jar\" \"$result.tmp\" \"$result.out\" \"$result-diff.tmp\" \"$jexampath".DIRECTORY_SEPARATOR."options\"") == "Two files are identical")
                return true;
            else
                return false;
    }
    return false;
}


/**
 * Inserts passed test to global <b>$dom<b>
 *
 * @param string $path Path to test source file
 * @param DOMDocumentFragment|bool $pass Pass HTML fragment
 * @param int $rc Test return code
 * @param int $indent Amount of indentation to generate for By folder view
 * @param string $test_name Name of the test to generate in By folder view
 * @param string $latest_folder Latest folder for correct JS folder structure
 * @return void
 */
function DOM_insert_pass(string $path, DOMDocumentFragment|bool $pass, int $rc, int $indent, string $test_name, string $latest_folder): void
{
    global  $dom;
    $pass->childNodes[1]->childNodes[1]->textContent = "$path";
    $pass->childNodes[1]->childNodes[3]->textContent = "$rc";
    $pass->childNodes[1]->childNodes[5]->textContent = "Passed";
    $pass2 = clone $pass;
    $dom->getElementById("passed")->childNodes[3]->appendChild($pass);

    $pass2->childNodes[1]->childNodes[1]->textContent = structure($indent) . $test_name;
    try{
        $ele = $dom->createElement('td');
    }
    catch(Exception $e){
        fwrite(STDERR, "Caught exeption:".$e->getMessage()."\n");
        exit(99);
    }
    //$ele->setAttribute("class","test");
    $pass2->childNodes[1]->setAttribute("class", "$latest_folder pass");
    $pass2->childNodes[1]->insertBefore($ele, $pass2->childNodes[1]->firstChild);
    $dom->getElementById("folders")->appendChild($pass2);
}

/**
 * Inserts code block from <b>$file_to_code</b> to dom <b>$fail</b> at <b>$child</b>
 *
 * @param string $file_to_code File data for insertion in global <b>$dom
 * @param DOMDocumentFragment|bool $fail Fail HTML fragment
 * @param int $child Where to add the file popup (left, center, right) -> (7, 9, 11)
 * @return void
 */
function DOM_insert_codeBlock(string $file_to_code, DOMDocumentFragment|bool $fail, int $child): void
{
    global $dom;
    $temp = fopen($file_to_code, "r");
    $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[3]->childNodes[1]->childNodes[$child]->append("    ");
    while (($line = fgets($temp))) {
        $br = $dom->createDocumentFragment();
        $br->appendXML('<br/>');
        $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[3]->childNodes[1]->childNodes[$child]->append(preg_replace("(\n)", "", $line));
        $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[3]->childNodes[1]->childNodes[$child]->appendChild($br);
    }
    $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[3]->childNodes[1]->childNodes[$child]->append("\n                                                ");
    fclose($temp);
}

/**
 * Inserts failed test to global <b>$dom<b>
 *
 * @param DOMDocumentFragment|bool $fail Pass HTML fragment
 * @param int $indent Amount of indentation to generate for By folder view
 * @param string $test_name Name of the test to generate in By folder view
 * @param string $latest_folder Latest folder for correct JS folder structure
 * @return void
 */
function DOM_insert_fail(DOMDocumentFragment|bool $fail, int $indent, string $test_name, string $latest_folder): void
{
    global $dom;
    $fail2 = clone $fail;
    $dom->getElementById("failed")->childNodes[3]->appendChild($fail);

    $fail2->childNodes[1]->childNodes[1]->textContent = structure($indent) . $test_name;
    try{
        $ele = $dom->createElement('td');
    }
    catch(Exception $e){
        fwrite(STDERR, "Caught exeption:".$e->getMessage()."\n");
        exit(99);
    }
    $ele->setAttribute("class","test");

    $string = $fail2->childNodes[1]->childNodes[1]->getAttribute("onclick");
    $replace = substr_replace($string,"T",-2,0);
    $fail2->childNodes[1]->childNodes[1]->setAttribute("onclick",$replace);

    $string = $fail2->childNodes[1]->childNodes[5]->childNodes[1]->getAttribute("id");
    $fail2->childNodes[1]->childNodes[5]->childNodes[1]->setAttribute("id",$string."T");

    $string = $fail2->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[1]->childNodes[3]->getAttribute("onclick");
    $replace = substr_replace($string,"T",-2,0);
    $fail2->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[1]->childNodes[3]->setAttribute("onclick",$replace);

    $fail2->childNodes[1]->setAttribute("class", "$latest_folder fail");
    $fail2->childNodes[1]->insertBefore($ele, $fail2->childNodes[1]->firstChild);

    $dom->getElementById("folders")->appendChild($fail2);
}

/** Pregenerates HTML fragments <b>$pass</b> and <b>$fail</b>
 *
 * @param int $testNO Test number
 * @param DOMDocumentFragment|bool $pass Pass HTML fragment
 * @param DOMDocumentFragment|bool $fail Fail HTML fragment
 * @return void
 */
function pregen_HTML(int $testNO ,DOMDocumentFragment|bool &$pass ,DOMDocumentFragment|bool &$fail): void
{
    global $dom;
    $pass = $dom->createDocumentFragment();
    $data = '    <tr>
                            <td></td>
                            <td class="RC"></td>
                            <td></td>
                        </tr>
                    ';
    $pass->appendXML($data);
    $fail = $dom->createDocumentFragment();
    $data = '    <tr>
                            <td class="popout" onclick="show_result(\'R' . $testNO . '\')">
                                basic.src
                            </td>
                            
                            <td class="RC">0</td>
                            <td>Output xml diference
                                <div id="R' . $testNO . '" class="popup">
                                    <div class="pop_content">
                                        <div class="pop_menu">
                                            <div class="left"></div>
                                            <div class="right" onclick="hide(\'R' . $testNO . '\')">X</div>
                                        </div>
                                        <div class="pop_text">
                                            <div class="text_devider">   
                                                <div class="GH">Source file</div>
                                                <div class="GH">Correct output</div>
                                                <div class="GH">Your output</div>
                                                <div class="tex src">
                                                </div>
                                                <div class="tex ref_out">
                                                </div>
                                                <div class="tex out">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    ';
    $fail->appendXML($data);
}

/**
 *
 * @param mixed $result
 * @param string $test_name
 * @param string $latest_folder
 * @param int $i Folder depth for generating folder indents
 * @return void
 */
function get_all_test_folders( mixed $result, string &$test_name, string &$latest_folder,int &$i): void
{
    global $dom;
    $folders = explode(DIRECTORY_SEPARATOR, $result);
    $latest_folder = "";
    for ($i = 0; $i < count($folders) - 1; $i++) {
        $latest_folder = $folders[$i];
        if ($dom->getElementById("$folders[$i]") == null) {
            //echo "this";
            $dom_folder = $dom->createDocumentFragment();
            $prev = "";
            if ($i > 0) {
                $prev = $folders[$i - 1];
            }
			
			if ($folders[$i] != "")
			{
				$data = '<tr id="' . $folders[$i] . '" class="' . $prev . '">
                            <td onclick="drop_toggle(this,\'' . $folders[$i] . '\')" class="open">/\</td>
                            <td></td>
                            <td class="RC"></td>
                            <td></td>
                        </tr>
                    ';
				$dom_folder->appendXML($data);
				$element = $dom->getElementById("folders")->appendChild($dom_folder);
				$element->setIDAttribute('id', true);
				$element->childNodes[3]->textContent = structure($i) . $folders[$i];
			}
            
        }
    }
    $test_name = end($folders);
}

/**
 * @param mixed $result
 * @param string $exe
 * @param DOMDocumentFragment|bool $pass
 * @param int $rc
 * @param int $indent
 * @param string $test_name
 * @param string $latest_folder
 * @param DOMDocumentFragment|bool $fail
 * @return void
 */
function DOM_test_output_identity(mixed $result, string $exe,DOMDocumentFragment|bool $pass, int $rc, int $indent, string $test_name, string $latest_folder,DOMDocumentFragment|bool $fail): void
{
    global $passed_count,$error_count;
    if (files_are_identical("$result.out", "$result.tmp", $exe)) {
        DOM_insert_pass($result, $pass, $rc, $indent, $test_name, $latest_folder);
        $passed_count++;
    } else {
        $fail->childNodes[1]->childNodes[1]->textContent = "$result";
        $fail->childNodes[1]->childNodes[3]->textContent = "$rc";
        $fail->childNodes[1]->childNodes[5]->childNodes[0]->textContent = "Output diference error" . "\n                                ";
        $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[1]->childNodes[1]->textContent = "$result";

        DOM_insert_codeBlock("$result.src", $fail, 7);
        DOM_insert_codeBlock("$result.out", $fail, 9);
        DOM_insert_codeBlock("$result.tmp", $fail, 11);

        DOM_insert_fail($fail, $indent, $test_name, $latest_folder);
        $error_count++;
    }
}

/**
 * @param mixed $result
 * @param DOMDocumentFragment|bool $fail
 * @param int $rc
 * @param bool|string $correct_rc
 * @param int $indent
 * @param string $test_name
 * @param string $latest_folder
 * @return void
 */
function DOM_insert_fail_RC(mixed $result, DOMDocumentFragment|bool $fail, int $rc, bool|string $correct_rc, int $indent, string $test_name, string $latest_folder): void
{
    $fail->childNodes[1]->childNodes[1]->textContent = "$result";
    $fail->childNodes[1]->childNodes[3]->textContent = "$rc";
    $fail->childNodes[1]->childNodes[5]->childNodes[0]->textContent = "Wrong RC" . "\n                                ";
    $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[1]->childNodes[1]->textContent = "$result";

    DOM_insert_codeBlock("$result.src", $fail, 7);

    $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[3]->childNodes[1]->childNodes[9]->append("$correct_rc");

    if($rc == 1)
    {
        DOM_insert_codeBlock("$result-err.tmp", $fail, 11);
    }
    else{
        $fail->childNodes[1]->childNodes[5]->childNodes[1]->childNodes[1]->childNodes[3]->childNodes[1]->childNodes[11]->append("$rc");
    }

    DOM_insert_fail($fail, $indent, $test_name, $latest_folder);
}

//---------------------------------MAIN PROGRAM START----------------------------------------------

//------Generate new DOM for HTML output
$dom = new DOMDocument('1.0');
$dom->encoding = "UTF-8";
$dom->formatOutput = true;

//------Pregenerate CSS for HTML---------
$css='
            :root{
                --main_width: 1000px;
                --progress_fill: rgb(180, 11, 11);
                animation: colors 2s ease both;
            }
            *{box-sizing: border-box;}
            div{display: block;}
            body{
                margin: 0;
                padding: 0;
            }
            #main{
                width:fit-content;
                margin: auto;
                cursor: default;
            }
            .select{
                width: var(--main_width);
                min-height: 150px;
                height: -webkit-fill-available;
                max-height: 70%;
                visibility: hidden;
                position: absolute;
                overflow: auto;
            }
            .select table thead{
                position: sticky;
                top: 0;
                background-color: antiquewhite;
            }
            .viz{
                visibility: visible;
            }
            #menu{
                display: flex;
                width: var(--main_width);
            }
            #menu div{
                float: left;
                padding: 3px;
                border: 1px;
                border-color: black;
                border-style: solid;
                box-sizing: content-box;
                cursor: pointer;
            }
            #basic_info{
                width:var(--main_width);
                background-color: greenyellow;
                display: flex;
            }
            .active{
                background-color: darkgray;
            }
            #percent{
                padding:3px;
                display: flex;
                margin: auto;
            }
            .text_right{
                text-align: right;
            }
            .left{
                float: left;
            }
            .result_table{
                border-collapse: collapse;
                width: 100%;
            }
            .RC{
                width: 30px;
            }
            .fail{
                width:30%;
            }
            .popout{
                cursor: pointer;
            }
            .popup{
                background-color: rgba(0, 0, 0, 75%);
                width:100%;
                height: 100%;
                position: fixed;
                top: 0;
                left: 0;
                z-index: 10;
                visibility: hidden;
            }
            .pop_content{
                width:75%;
                height:60%;
                margin: auto;
                position: relative;
                top: 50%;
                display: flex;
                flex-direction: column;
                -ms-transform: translateY(-50%);
                transform: translateY(-50%);   
            }
            .pop_text{
                background-color: #ffffff;
                height: 100%;
            }
            .pop_menu .right{
                background-color: #ffffff;
                padding: 3px;
                float: right;
                cursor: pointer;
            }
            .pop_menu .left{
                background-color: #ffffff;
                padding: 3px;
                float: left;
            }
            .visib{
                visibility: visible;
            }
            #failed tr{
                background-color: #c05454;
                height: 1.5em;
            }
            #failed tr td{
                padding: 3px;
            }
            #failed tbody tr:nth-child(odd) {
                background-color: #b97c7c;
            }
            #passed tr{
                background-color: #54c059;
                height: 1.5em;
            }
            #passed tr td{
                padding: 3px;
            }
            #passed tbody tr:nth-child(odd) {
                background-color: #7cb97f;
            }
            #folder tr{
                height: 1.5em;
            }
            #folder tr td{
                padding: 3px;
            }
            .pass{
                background-color: #7cb97f;
            }
            .fail{
                background-color: #b97c7c;
            }
            .mix{
                background-color: #d4ce62;
            }
            #folder tbody tr:nth-child(odd) .mix{
                background-color: #e7b940;
            }
            #folder tbody tr:nth-child(even) .mix{
                background-color: #d4ce62;
            }
            .text_devider td
            {
                background-color: #ffffff;
                width: 33%;
            }
            .text_devider{
                padding: 3px;
                display: grid;
                grid-template-columns: 33% 33% 33%;
                grid-template-rows: max-content;
                grid-gap: 3px;
                overflow: hidden;
                height: 100%;
                background-color: #bebebe;
            }
            .GH{
                background-color: #bebebe;
            }
            .text_devider .tex{
               overflow: auto;
               background-color: #ffffff;
               font-family: monospace;
               font-size: small;
               padding-left: 2px;
            }
            .hidden{
                visibility: hidden;
                position: absolute;
            }
            #folders tr{
                font-family: monospace;
            }
            .cursor{
                cursor: pointer;
            }
            

            .circular{
                height:100px;
                width: 100px;
                position: relative;
            }
            .circular .inner{
                position: absolute;
                z-index: 6;
                top: 50%;
                left: 50%;
                height: 80px;
                width: 80px;
                margin: -40px 0 0 -40px;
                background: #ffffff;
                border-radius: 100%;
            }
            .circular .number{
                position: absolute;
                top:50%;
                left:50%;
                transform: translate(-50%, -50%);
                z-index: 9;
                font-size:18px;
                font-weight:500;
                color:var(--progress_fill);
            }
            .circular .bar{
                position: absolute;
                height: 100%;
                width: 100%;
                border-radius: 100%;
                -webkit-border-radius: 100%;
                clip: rect(0px, 100px, 100px, 50px);
            }
            .circle .bar .progress{
                position: absolute;
                height: 100%;
                width: 100%;
                border-radius: 100%;
                -webkit-border-radius: 100%;
                clip: rect(0px, 50px, 100px, 0px);
                background: var(--progress_fill);
            }
            .circle .right {
                animation: left 2s ease both;
                z-index:3;
            }
            .circle .left .progress{
                z-index:1;
                animation: left 2s ease both;
            }
            .circle .right .progress{
                animation: right 2s ease both;
                animation-delay:0.5s;
            }
            
            @-moz-document url-prefix() {
                #main { width : -moz-fit-content;}
            } 
';


//-----------Pregenerate backbone HTML for output-------------
$dom->loadHTML('<!DOCTYPE html>
<html lang="cs">
    <head>
        <meta charset="UTF-8">
        <title>
            Výstup testů
        </title>
        <style id="css">
        </style>
        <script>
            function test(p1,ele)
            {
                //menu toggle
                let elements=document.getElementsByClassName("active");                
                for (let i = 0; i < elements.length; i++) {
                    elements[i].classList.remove("active");
                }
                ele.classList.add("active");

                elements= document.getElementsByClassName("viz");                
                for (let i = 0; i < elements.length; i++) {
                    elements[i].classList.remove("viz");
                }
                document.getElementById("s"+p1.toString()).classList.add("viz");
            }

            //hide/show more test info 
            function hide(tohide)
            {
                document.getElementById(tohide).classList.remove("visib");   
            }
            function show_result(toshow)
            {
                document.getElementById(toshow).classList.add("visib");   
            }
            
            function hide_all_subs(folder)
            {
                //Recursion end
                if(folder.length === 0)
                {
                    return;
                }
                let elem = folder.pop();
                
                //get all sub files/folders
                const elements = document.getElementsByClassName(elem);
                for(let i = 0; i < elements.length;i++)
                    {
                        //hide files/folders, add folder for recursion
                        if(!elements[i].id)
                        {
                            elements[i].classList.add("hidden");
                            continue;
                        }
                        if(!elements[i].classList.contains("hidden"))
                        {
                            folder.push(elements[i].id);
                        }
                    }
                //hide folders
                document.getElementById(elem).classList.add("hidden");
                return hide_all_subs(folder);
            }

            function show_all_subs(folder)
            {
                if(folder.length === 0)
                {
                    return;
                }
                let elem = folder.pop();
                
                const elements = document.getElementsByClassName(elem);
                for(let i = 0; i < elements.length;i++)
                    {
                        //show all files/folder if folder was open add it to recursion
                        elements[i].classList.remove("hidden");
                        if(elements[i].childNodes[1].classList.contains("open"))
                        {
                            folder.push(elements[i].id);
                        }
                    }
                return show_all_subs(folder);
            }
            
            function drop_toggle(th,toggle)
            {
                //array of all folders to toggle visibility
                let all_sub = [];
                all_sub.push(toggle);
                if(th.classList.contains("open"))
                {
                    hide_all_subs(all_sub);
                    document.getElementById(toggle).classList.remove("hidden");
                    th.classList.remove("open");
                      th.textContent="\\\\/";
                }else
                {
                    show_all_subs(all_sub);
                    th.classList.add("open");
                    th.textContent="/\\\\";
                }
            }
            
            window.addEventListener("load", function(){
                //Add colors to tests ordered by folder
                const dir_tests = document.getElementById("folders").childNodes;
                let dir_list = [];
                //push all dirs to stack
                for (let i = 0; i < dir_tests.length; i++) {
                    if(dir_tests[i].id)
                    {
                        dir_list.push(dir_tests[i].id);
                    }
                }
                //important to store original length because it will change
                const len = dir_list.length;
                for (let i = 0; i<len;i++)
                {
                    //pop dir from end and evaluate color
                    const dir = dir_list.pop();
                    const to_check = document.getElementsByClassName(dir);

                    let fail = 0;
                    let pass = 0;
                    let mix = 0;
                    for(let j=0; j<to_check.length;j++)
                    {
                        if(to_check[j].classList.contains("fail"))
                        {
                            fail++;
                        }
                        if(to_check[j].classList.contains("pass"))
                        {
                            pass++;
                        }
                        if(to_check[j].classList.contains("mix"))
                        {
                            mix++;
                        }
                    }

                    if(mix>0)
                    {
                        document.getElementById(dir).classList.add("mix");
                    }
                    else if(fail !== 0 && pass !== 0)
                    {
                        document.getElementById(dir).classList.add("mix");
                    }
                    else if(fail > 0)
                    {
                        document.getElementById(dir).classList.add("fail");
                    }
                    else if(pass > 0)
                    {
                        document.getElementById(dir).classList.add("pass");
                    }
                    else{
                        console.log("error");
                    }
                }
            });
        </script>
    </head>
    <body>
        <div id="main">
            <div id="basic_info">
                <div id="percent">
                    <div class="circular">
                        <div class="inner"></div>
                        <div id="num" class="number">100%</div>
                        <div class="circle">
                            <div class="bar left">
                                <div class="progress"></div>
                            </div>
                            <div class="bar right">
                                <div class="progress"></div>
                            </div>
                        </div>
                    </div>
                    <table id="test_amount">
                        <tbody>
                            <tr>
                                <td class="text_right">
                                    Tests Total:
                                </td>
                                <td id="total">
                                    100
                                </td>
                            </tr>
                            <tr>
                                <td class="text_right">
                                    Tests Passed:
                                </td>
                                <td id="pass">
                                    75
                                </td>
                            </tr>
                            <tr>
                                <td class="text_right">
                                    Tests Failed:
                                </td>
                                <td id="fail">
                                    25
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="menu">
                <div class="active" onclick="test(1,this)">Failed</div>
                <div class="" onclick="test(2,this)">Passed</div>
                <div class="" onclick="test(3,this)">By folder</div>
            </div>
            <div id="s1" class="select viz">
                <table id="failed" class="result_table">
                    <thead>
                        <tr>
                            <td>Test name:</td>
                            <td class="RC">RC</td>
                            <td>Failiure</td>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div id="s2" class="select">
                <table id="passed" class="result_table">
                    <thead>
                        <tr>
                            <td>Test name:</td>
                            <td class="RC">RC</td>
                            <td>Failiure</td>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div id="s3" class="select">
                <table id="folder" class="result_table">
                    <thead>
                        <tr>
                            <td style="width: 40px"></td>
                            <td>Test name:</td>
                            <td class="RC">RC</td>
                            <td>Failiure</td>
                        </tr>
                    </thead>
                    <tbody id="folders">
                    </tbody>
                </table>
            </div>
        </div>
    </body>
</html>');



$test_name = "";
$latest_folder = "";
$indent = 0;
$results = [];

$cwd = getcwd().DIRECTORY_SEPARATOR;
$parse_path = $cwd."$parse_script";

$count = 0;

//get all files with file paths
get_files_from_dir($test_dir, $recursive,$results);

$test_count=count($results);
$dom->getElementById("total")->textContent=$test_count;

if($thread_count < 1)
{
    $thread_count = ceil($test_count/200);
}

//split the work to threads
echo "Test amount: ",$test_count;

$work = array_chunk($results,($test_count/$thread_count)+1);

$childs = [];

for ($x = 0; $x < $thread_count; $x++) {
    switch ($pid = pcntl_fork()) {
        case -1:
            // @fail
            die('Fork failed');
            break;

        case 0:
            // @child: Include() misbehaving code here
            print "FORK: Child #{$x}\n";

            $dom = new DOMDocument('1.0');
            $dom->encoding = "UTF-8";
            $dom->formatOutput = true;

            $dom->loadHTML('
                    <table id="failed" class="result_table">
                    <thead>
                        <tr>
                            <td>Test name:</td>
                            <td class="RC">RC</td>
                            <td>Failiure</td>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <table id="passed" class="result_table">
                    <thead>
                        <tr>
                            <td>Test name:</td>
                            <td class="RC">RC</td>
                            <td>Failiure</td>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <table id="folder" class="result_table">
                    <thead>
                        <tr>
                            <td style="width: 40px"></td>
                            <td>Test name:</td>
                            <td class="RC">RC</td>
                            <td>Failiure</td>
                        </tr>
                    </thead>
                    <tbody id="folders">
                    </tbody>
                </table>
                ');

            if($parse_only) {
                foreach ($work[$x] as $result) {
                    $testNO++;
                    //pre generate $pass and $fail HTML_DOM
                    pregen_HTML($testNO, $pass, $fail);

                    //get all folders from path for by folder output
                    get_all_test_folders($result, $test_name, $latest_folder, $indent);

                    //Write percentage of tests done to stdout if outfile is present
                    if ($outfile && ($testNO / count($work[$x]) * 100) > $percent) {
                        $percent += $percent_interval;
                        echo "Thread $x: PASSED $passed_count/" . ($testNO - 1) . ", ERROR $error_count\n";
                        echo "Thread $x: DONE " . floor(($testNO / count($work[$x]) * 100)) . "%\n";
                    }
                    unset($output);
					
                    //run the tested parse script
                    exec("\"$python_path\" \"$parse_script\" <\"$result.src\" 2>\"$result-err.tmp\" >\"$result.tmp\"", $output, $rc);
					

                    $correct_rc = file_get_contents("$result.rc");
                    if ($correct_rc != 0 and $correct_rc == $rc) {
                        //echo "Test $result proběhl úspěšně\n";
                        DOM_insert_pass($result, $pass, $rc, $indent, $test_name, $latest_folder);
                        $passed_count++;
                        continue;
                    }
                    if ($rc != $correct_rc) {
                        DOM_insert_fail_RC($result, $fail, $rc, $correct_rc, $indent, $test_name, $latest_folder);
                        $error_count++;
                        continue;
                    }

                    DOM_test_output_identity($result, "JEXAM", $pass, $rc, $indent, $test_name, $latest_folder, $fail);

                }
            }

            if($int_only)
            {
                foreach ($work[$x] as $result)
                {
                    $testNO++;
                    //pre generate $pass and $fail HTML_DOM
                    pregen_HTML($testNO, $pass , $fail);

                    //get all folders from path for by folder output
                    get_all_test_folders($result,$test_name, $latest_folder, $indent);

                    //Write percentage of tests done to stdout if outfile is present
                    if($outfile && ($testNO/$test_count*100)>$percent)
                    {
                        $percent += $percent_interval;

                        echo "PASSED $passed_count/".($testNO-1).", ERROR $error_count\n";
                        echo "DONE ".floor(($testNO/$test_count*100))."%\n";
                    }
                    unset($output);
                    //run the tested parse script
                    exec("\"$php_path\" \"$int_script\" --input=\"$result.in\" <\"$result.src\" 2>\"$result-err.tmp\" >\"$result.tmp\"",$output,$rc);
                    //file_put_contents("$result.tmp",$output);
                    $correct_rc = file_get_contents("$result.rc");
                    if($correct_rc!=0 and $correct_rc == $rc)
                    {
                        //echo "Test $result proběhl úspěšně\n";
                        DOM_insert_pass($result, $pass, $rc, $indent, $test_name, $latest_folder);
                        $passed_count++;
                        continue;
                    }
                    if($rc != $correct_rc)
                    {
                        DOM_insert_fail_RC($result, $fail, $rc, $correct_rc, $indent, $test_name, $latest_folder);
                        $error_count++;
                        continue;
                    }

                    $exe = $WIN ? "WIN" : "UNIX";
                    DOM_test_output_identity($result,$exe, $pass, $rc, $indent, $test_name, $latest_folder, $fail);
                }
            }

            if($int_only == false && $parse_only == false)
            {
                foreach ($work[$x] as $result)
                {
                    $testNO++;
                    //pre generate $pass and $fail HTML_DOM
                    pregen_HTML($testNO, $pass , $fail);

                    //get all folders from path for by folder output
                    get_all_test_folders($result, $test_name, $latest_folder, $indent);

                    //Write percentage of tests done to stdout if outfile is present
                    if($outfile && ($testNO/$test_count*100)>$percent)
                    {
                        $percent += $percent_interval;
                        echo "PASSED $passed_count/".($testNO-1).", ERROR $error_count\n";
                        echo "DONE ".floor(($testNO/$test_count*100))."%\n";
                    }
                    unset($output);
                    //run the tested parse script and pipe output to interpret
                    exec("\"$python_path\" \"$parse_script\" <\"$result.src\" 2>\"$result-err-php.tmp\" | \"$php_path\" \"$int_script\" --input=\"$result.in\" 2>\"$result-err.tmp\" >\"$result.tmp\"",$output,$rc);

                    $correct_rc = file_get_contents("$result.rc");
                    if($correct_rc!=0 and $correct_rc == $rc)
                    {
                        //echo "Test $result proběhl úspěšně\n";
                        DOM_insert_pass($result, $pass, $rc, $indent, $test_name, $latest_folder);
                        $passed_count++;
                        continue;
                    }
                    if($rc != $correct_rc)
                    {
                        DOM_insert_fail_RC($result, $fail, $rc, $correct_rc, $indent, $test_name, $latest_folder);
                        $error_count++;
                        continue;
                    }

                    $exe = $WIN ? "WIN" : "UNIX";
                    DOM_test_output_identity($result,$exe, $pass, $rc, $indent, $test_name, $latest_folder, $fail);
                }
            }


            $dom->formatOutput = true;
            if(!is_dir("./tmp"))
            {
                mkdir("./tmp");
            }
            $dom->saveHTMLFile("./tmp/html_stub$x.html");

            exit(0);

        default:
            // @parent
            print "FORK: Parent, letting the child run amok...\n";
            $childs[] = $pid;
            //pcntl_waitpid($pid, $status);
            break;
    }
}

while(count($childs) > 0)
{
    foreach($childs as $key => $pid) {
        $res = pcntl_waitpid($pid, $status, WNOHANG);

        // If the process has already exited
        if($res == -1 || $res > 0)
            unset($childs[$key]);
    }
    usleep(100000);
}

echo "DONE Testing gathering results.\n";

$dom_stub = new DOMDocument('1.0');
$dom_stub->encoding = "UTF-8";
$dom_stub->formatOutput = true;

$error_count = 0;
$passed_count = 0;

for($x = 0; $x < $thread_count; $x++){
    $dom_stub->loadHTMLFile("./tmp/html_stub$x.html");

    foreach ($dom_stub->getElementById("failed")->childNodes[3]->childNodes as $node)
    {
        $dom->getElementById("failed")->childNodes[3]->appendChild($dom->importNode($node, true));
        if($node->localName == "tr"){
            $error_count ++;
        }
    }

    foreach ($dom_stub->getElementById("passed")->childNodes[3]->childNodes as $node)
    {
        $dom->getElementById("passed")->childNodes[3]->appendChild($dom->importNode($node, true));
        if($node->localName == "tr"){
            $passed_count ++;
        }

    }

    foreach ($dom_stub->getElementById("folders")->childNodes as $node)
    {
        if($node->nodeName == "tr")
        {
            if($dom->getElementById($node->getAttribute("id")) != null)
            {
                continue;
            }
        }

        $dom->getElementById("folders")->appendChild($dom->importNode($node, true));
    }

    //$dom->getElementById("folders")->after($dom_stub->getElementById("folders")->childNodes);
}


if(!$no_clean)
{
    //different cleanup on WIN and UNIX
    if($WIN)
    {
        exec('DEL /S /Q *.tmp');
    }
    else
    {
        exec('find . -name "*.tmp" -exec rm -f {} \;');
    }
}

//insert pass and fail count to DOM
$dom->getElementById("pass")->textContent=$passed_count;
$dom->getElementById("fail")->textContent=$error_count;

//Get pass percentage and generate little animation
$passed_percent = floor(($passed_count/$test_count)*100);
$dom->getElementById("num")->textContent=$passed_percent.'%';
$rot = floor(($passed_percent/100)*180);
$keyframes = '
            @keyframes left{  
                100%{
                    transform: rotate('.$rot.'deg);
                    
                }
            }
            @keyframes right{
                100%{
                    transform: rotate('.$rot.'deg);
                }
            }
            @keyframes colors{';

if($passed_percent<45) {
    $keyframes = $keyframes . '
                100%{
                    --progress_fill:#b40b0b;
                }
            }
        ';
}
elseif ($passed_percent<75) {
    $keyframes = $keyframes . '
                60%{
                    --progress_fill:#b40b0b;
                }
                100%%{
                    --progress_fill:#b4a30b;
                }
            }
        ';
}
else {
    $keyframes = $keyframes . '
                20%{
                    --progress_fill:#b40b0b;
                }
                50%{
                    --progress_fill:#b4a30b;
                }
                100%{
                    --progress_fill:#0bb40b;
                }
            }
        ';
}

//insert keyframes
$dom->getElementById("css")->textContent=$css.$keyframes;

//print out outfile or generate it
if($outfile)
{
    echo "Prošlo $passed_count/$testNO, Chyba $error_count\n";
    echo "DONE ".floor(($testNO/$test_count*100))."%\n";
    if($WIN)
    {
        exec('DEL '.$outfile);
    }
    else
    {
        exec('rm '.$outfile);
    }
    $dom->formatOutput = true;
    $dom->saveHTMLFile($outfile);
}else
{
    echo $dom->saveHTML();
}

