<?php

class JSONGanttConnector
{
	private $res;
	private $dbtype;

	function __construct ($res,$dbtype)
	{
		$this->res = $res;
		$this->dbtype = $dbtype;
	}

	function render_links($table)
	{
	}
}

class GanttConnector
{
	private $res;
	private $dbtype;
	private $xw;

	function __construct ($res,$dbtype)
	{
		$this->res = $res;
		$this->dbtype = $dbtype;

		$this->xw = new XMLWriter ();
		$this->xw->openMemory ();
		$this->xw->startDocument ("1.0");
		$this->xw->startElement ("data");
		$this->xw->setIndent (TRUE);
		$this->xw->setIndentString ("\t");
	}

	function render ()
	{
		$this->xw->endElement ();
		echo $this->xw->outputMemory ();
	}

	function render_links ($table_name, $id_name, $field_names)
	{
		$results = $this->res->query ("select $field_names from $table_name;");

		$this->xw->startElement("coll_options");
		$this->xw->startAttribute("for");
		$this->xw->text("links");
		$this->xw->endAttribute();
		while ($row = $results->fetchArray(SQLITE3_ASSOC))
		{
			//var_dump ($row);
			$this->xw->startElement("item");
			foreach ($row as $k => $v)
			{
				//echo "$k => $v\n";
				$this->xw->startAttribute($k);
				$this->xw->text($v);
				$this->xw->endAttribute();
			}
			$this->xw->endElement();
		}
		$this->xw->endElement();
	}

	function render_table ($table_name, $id_name, $field_names)
	{
		$results = $this->res->query ("select $id_name,$field_names from $table_name;");

		while ($row = $results->fetchArray(SQLITE3_ASSOC))
		{
			//var_dump ($row);
			$this->xw->startElement("task");
			$this->xw->startAttribute("id");
			$this->xw->text(array_shift ($row));
			$this->xw->endAttribute();
			foreach ($row as $k => $v)
			{
				//echo "$k => $v\n";
				$this->xw->startElement($k);
				$this->xw->startCdata();
				$this->xw->text($v);
				$this->xw->endCdata();
				$this->xw->endElement();
			}
			$this->xw->endElement();
		}
	}

	function mix ($a, $b)
	{
	}
}

?>
