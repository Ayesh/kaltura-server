<?php

require_once 'propel/engine/builder/om/php5/PHP5PeerBuilder.php';

/**
 * Generates a PHP5 base Peer class for user object model (OM).
 *
 * This class produces the base peer class (e.g. BaseMyPeer) which contains all
 * the custom-built query and manipulator methods.
 *
 * @package    infra.propel.php5
 */
class KalturaPeerBuilder extends PHP5PeerBuilder 
{	
	const KALTURA_COLUMN_PARTNER_ID = 'partner_id';
	const KALTURA_COLUMN_DISPLAY_IN_SEARCH = 'display_in_search';
	
	/**
	 * Adds the doCount() method.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoCount(&$script)
	{
		$script .= "
	/**
	 * Returns the number of rows matching criteria.
	 *
	 * @param      Criteria \$criteria
	 * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
	 * @param      PropelPDO \$con
	 * @return     int Number of matching rows.
	 */
	public static function doCount(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null)
	{
		// we may modify criteria, so copy it first
		\$criteria = clone \$criteria;

		// We need to set the primary table name, since in the case that there are no WHERE columns
		// it will be impossible for the BasePeer::createSelectSql() method to determine which
		// tables go into the FROM clause.
		\$criteria->setPrimaryTableName(".$this->getPeerClassname()."::TABLE_NAME);

		if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
			\$criteria->setDistinct();
		}

		if (!\$criteria->hasSelectClause()) {
			".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		}

		\$criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count
		\$criteria->setDbName(self::DATABASE_NAME); // Set the correct dbName
		";
		
		// apply behaviors
    $this->applyBehaviorModifier('preSelect', $script);
    
		$script .= "
		// BasePeer returns a PDOStatement
		\$stmt = ".$this->getPeerClassname()."::doCountStmt(\$criteria, \$con);

		if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$count = (int) \$row[0];
		} else {
			\$count = 0; // no rows returned; we infer that means 0 matches.
		}
		\$stmt->closeCursor();
		return \$count;
	}";
	}
	
	
	/**
	 * Adds the doSelectStmt() method.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoSelectStmt(&$script)
	{

		$script .= "

	public static function alternativeCon(\$con)
	{
		if(\$con === null)
			\$con = myDbHelper::alternativeCon(\$con);
			
		if(\$con === null)
			\$con = Propel::getConnection(".$this->getPeerClassname()."::DATABASE_NAME, Propel::CONNECTION_READ);
		
		return \$con;
	}
		
	/**
	 * @var criteriaFilter The default criteria filter.
	 */
	protected static \$s_criteria_filter;
	
	public static function  setUseCriteriaFilter ( \$use )
	{
		\$criteria_filter = ".$this->getPeerClassname()."::getCriteriaFilter();
		
		if ( \$use )  \$criteria_filter->enable(); 
		else \$criteria_filter->disable();
	}
	
	/**
	 * Returns the default criteria filter
	 *
	 * @return     criteriaFilter The default criteria filter.
	 */
	public static function &getCriteriaFilter()
	{
		if(self::\$s_criteria_filter == null)
			".$this->getPeerClassname()."::setDefaultCriteriaFilter();
		
		return self::\$s_criteria_filter;
	}
	
	 
	/**
	 * Creates default criteria filter
	 */
	public static function setDefaultCriteriaFilter()
	{
		if(self::\$s_criteria_filter == null)
			self::\$s_criteria_filter = new criteriaFilter();
		
		\$c = new myCriteria(); 
		self::\$s_criteria_filter->setFilter(\$c);
	}
	
	
	/**
	 * the filterCriteria will filter out all the doSelect methods - ONLY if the filter is turned on.
	 * IMPORTANT - the filter is turend on by default and when switched off - should be turned on again manually .
	 * 
	 * @param      Criteria \$criteria The Criteria object used to build the SELECT statement.
	 */
	protected static function attachCriteriaFilter(Criteria \$criteria)
	{
		".$this->getPeerClassname()."::getCriteriaFilter()->applyFilter(\$criteria);
	}
	
	public static function addPartnerToCriteria(\$partnerId, \$privatePartnerData = false, \$partnerGroup = null, \$kalturaNetwork = null)
	{";
	
		$table = $this->getTable();
		$partnerIdColumn = $table->getColumn(self::KALTURA_COLUMN_PARTNER_ID);
		$displayInSearchColumn = $table->getColumn(self::KALTURA_COLUMN_DISPLAY_IN_SEARCH);
		
		if($partnerIdColumn)
		{
			$script .= "
		\$criteriaFilter = self::getCriteriaFilter();
		\$criteria = \$criteriaFilter->getFilter();
		
		if(!\$privatePartnerData)
		{
			// the private partner data is not allowed - 
			if(\$kalturaNetwork)
			{
				// allow only the kaltura netword stuff";
			
			if($displayInSearchColumn)
			{
				$script .= "
				\$criteria->addAnd(self::DISPLAY_IN_SEARCH , mySearchUtils::DISPLAY_IN_SEARCH_KALTURA_NETWORK);
				";
			}
			
			$script .= "
				if(\$partnerId)
				{
					\$orderBy = \"(\" . self::PARTNER_ID . \"<>{\$partnerId})\";  // first take the pattner_id and then the rest
					myCriteria::addComment(\$criteria , \"Only Kaltura Network\");
					\$criteria->addAscendingOrderByColumn(\$orderBy);//, Criteria::CUSTOM );
				}
			}
			else
			{
				// no private data and no kaltura_network - 
				// add a criteria that will return nothing
				\$criteria->addAnd(self::PARTNER_ID, Partner::PARTNER_THAT_DOWS_NOT_EXIST);
			}
		}
		else
		{
			// private data is allowed
			if(empty(\$partnerGroup) && empty(\$kalturaNetwork))
			{
				// the default case
				\$criteria->addAnd(self::PARTNER_ID, \$partnerId);
			}
			elseif (\$partnerGroup == myPartnerUtils::ALL_PARTNERS_WILD_CHAR)
			{
				// all is allowed - don't add anything to the criteria
			}
			else 
			{
				\$criterion = null;
				if(\$partnerGroup)
				{
					// \$partnerGroup hold a list of partners separated by ',' or \$kalturaNetwork is not empty (should be mySearchUtils::KALTURA_NETWORK = 'kn')
					\$partners = explode(',', trim(\$partnerGroup));
					foreach(\$partners as &\$p)
						trim(\$p); // make sure there are not leading or trailing spaces
	
					// add the partner_id to the partner_group
					\$partners[] = \$partnerId;
					
					\$criterion = \$criteria->getNewCriterion(self::PARTNER_ID, \$partners, Criteria::IN);
				}
				else 
				{
					\$criterion = \$criteria->getNewCriterion(self::PARTNER_ID, \$partnerId);
				}";
			
			if($displayInSearchColumn)
			{
				$script .= "	
				
				if(\$kalturaNetwork)
				{
					\$criterionNetwork = \$criteria->getNewCriterion(self::DISPLAY_IN_SEARCH, mySearchUtils::DISPLAY_IN_SEARCH_KALTURA_NETWORK);
					\$criterion->addOr(\$criterionNetwork);
				}";
			}
			
			$script .= "	
				
				\$criteria->addAnd(\$criterion);
			}
		}
			
		\$criteriaFilter->enable();";
			
		}
		
		$script .= "
	}
	
	/**
	 * Prepares the Criteria object and uses the parent doSelect() method to execute a PDOStatement.
	 *
	 * Use this method directly if you want to work with an executed statement durirectly (for example
	 * to perform your own object hydration).
	 *
	 * @param      Criteria \$criteria The Criteria object used to build the SELECT statement.
	 * @param      PropelPDO \$con The connection to use
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 * @return     PDOStatement The executed PDOStatement object.
	 * @see        ".$this->basePeerClassname."::doCount()
	 */
	public static function doCountStmt(Criteria \$criteria, PropelPDO \$con = null)
	{
		// attach default criteria
		".$this->getPeerClassname()."::attachCriteriaFilter(\$criteria);
		
		// set the connection to slave server
		\$con = ".$this->getPeerClassname()."::alternativeCon ( \$con );
		
		// BasePeer returns a PDOStatement
		return ".$this->basePeerClassname."::doCount(\$criteria, \$con);
	}
	
	
	/**
	 * Prepares the Criteria object and uses the parent doSelect() method to execute a PDOStatement.
	 *
	 * Use this method directly if you want to work with an executed statement durirectly (for example
	 * to perform your own object hydration).
	 *
	 * @param      Criteria \$criteria The Criteria object used to build the SELECT statement.
	 * @param      PropelPDO \$con The connection to use
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 * @return     PDOStatement The executed PDOStatement object.
	 * @see        ".$this->basePeerClassname."::doSelect()
	 */
	public static function doSelectStmt(Criteria \$criteria, PropelPDO \$con = null)
	{
		\$con = ".$this->getPeerClassname()."::alternativeCon(\$con);
		
		if (\$criteria->hasSelectClause()) 
		{
			\$asColumns = \$criteria->getAsColumns();
			if(count(\$asColumns) == 1 && isset(\$asColumns['_score']))
			{
				\$criteria = clone \$criteria;
				".$this->getPeerClassname()."::addSelectColumns(\$criteria);
			}
		}
		else
		{
			\$criteria = clone \$criteria;
			".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		}
		
		// Set the correct dbName
		\$criteria->setDbName(self::DATABASE_NAME);";
		// apply behaviors
		if ($this->hasBehaviorModifier('preSelect'))
		{
      $this->applyBehaviorModifier('preSelect', $script);
		}
		$script .= "

		// attach default criteria
		".$this->getPeerClassname()."::attachCriteriaFilter(\$criteria);
		
		// BasePeer returns a PDOStatement
		return ".$this->basePeerClassname."::doSelect(\$criteria, \$con);
	}";
	}

	/**
	 * Adds the doSelectJoin*() methods.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoSelectJoin(&$script)
	{
		$table = $this->getTable();
		$className = $this->getObjectClassname();
		$countFK = count($table->getForeignKeys());
		$join_behavior = $this->getJoinBehavior();

		if ($countFK >= 1) {

			foreach ($table->getForeignKeys() as $fk) {

				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

				if (!$joinTable->isForReferenceOnly()) {

					// This condition is necessary because Propel lacks a system for
					// aliasing the table if it is the same table.
					if ( $fk->getForeignTableName() != $table->getName() ) {

						$thisTableObjectBuilder = $this->getNewObjectBuilder($table);
						$joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
						$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);

						$joinClassName = $joinedTableObjectBuilder->getObjectClassname();

						$script .= "

	/**
	 * Selects a collection of $className objects pre-filled with their $joinClassName objects.
	 * @param      Criteria  \$criteria
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     array Array of $className objects.
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 */
	public static function doSelectJoin".$thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false)."(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
	{
		\$criteria = clone \$criteria;

		// Set the correct dbName if it has not been overridden
		if (\$criteria->getDbName() == Propel::getDefaultDB()) {
			\$criteria->setDbName(self::DATABASE_NAME);
		}

		".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol = (".$this->getPeerClassname()."::NUM_COLUMNS - ".$this->getPeerClassname()."::NUM_LAZY_LOAD_COLUMNS);
		".$joinedTablePeerBuilder->getPeerClassname()."::addSelectColumns(\$criteria);
";

            $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);
        		
            // apply behaviors
            $this->applyBehaviorModifier('preSelect', $script);
						
            $script .= "
		\$stmt = ".$this->getPeerClassname()."::doSelectStmt(\$criteria, \$con);
		\$results = array();

		while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$key1 = ".$this->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, 0);
			if (null !== (\$obj1 = ".$this->getPeerClassname()."::getInstanceFromPool(\$key1))) {
				// We no longer rehydrate the object, since this can cause data loss.
				// See http://propel.phpdb.org/trac/ticket/509
				// \$obj1->hydrate(\$row, 0, true); // rehydrate
			} else {
";
						if ($table->getChildrenColumn()) {
							$script .= "
				\$omClass = ".$this->getPeerClassname()."::getOMClass(\$row, 0);
				\$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
						} else {
							$script .= "
				\$cls = ".$this->getPeerClassname()."::getOMClass(false);
";
						}
						$script .= "
				" . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
				\$obj1->hydrate(\$row);
				".$this->getPeerClassname()."::addInstanceToPool(\$obj1, \$key1);
			} // if \$obj1 already loaded

			\$key2 = ".$joinedTablePeerBuilder->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, \$startcol);
			if (\$key2 !== null) {
				\$obj2 = ".$joinedTablePeerBuilder->getPeerClassname()."::getInstanceFromPool(\$key2);
				if (!\$obj2) {
";
						if ($joinTable->getChildrenColumn()) {
							$script .= "
					\$omClass = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(\$row, \$startcol);
					\$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
						} else {
							$script .= "
					\$cls = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(false);
";
						}

						$script .= "
					" . $this->buildObjectInstanceCreationCode('$obj2', '$cls') . "
					\$obj2->hydrate(\$row, \$startcol);
					".$joinedTablePeerBuilder->getPeerClassname()."::addInstanceToPool(\$obj2, \$key2);
				} // if obj2 already loaded
				
				// Add the \$obj1 (".$this->getObjectClassname().") to \$obj2 (".$joinedTablePeerBuilder->getObjectClassname().")";
					if ($fk->isLocalPrimaryKey()) {
						$script .= "
				// one to one relationship
				\$obj1->set" . $joinedTablePeerBuilder->getObjectClassname() . "(\$obj2);";
					} else {
					$script .= "
				\$obj2->add" . $joinedTableObjectBuilder->getRefFKPhpNameAffix($fk, $plural = false)."(\$obj1);";
					}
					$script .= "

			} // if joined row was not null

			\$results[] = \$obj1;
		}
		\$stmt->closeCursor();
		return \$results;
	}
";
					} // if fk table name != this table name
				} // if ! is reference only
			} // foreach column
		} // if count(fk) > 1

	} // addDoSelectJoin()
	
	
	/**
	 * Adds the doCountJoin*() methods.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoCountJoin(&$script)
	{
		$table = $this->getTable();
		$className = $this->getObjectClassname();
		$countFK = count($table->getForeignKeys());
		$join_behavior = $this->getJoinBehavior();

		if ($countFK >= 1) {

			foreach ($table->getForeignKeys() as $fk) {

				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

				if (!$joinTable->isForReferenceOnly()) {

					if ( $fk->getForeignTableName() != $table->getName() ) {

						$thisTableObjectBuilder = $this->getNewObjectBuilder($table);
						$joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
						$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);

						$joinClassName = $joinedTableObjectBuilder->getObjectClassname();

						$script .= "

	/**
	 * Returns the number of rows matching criteria, joining the related ".$thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false)." table
	 *
	 * @param      Criteria \$criteria
	 * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     int Number of matching rows.
	 */
	public static function doCountJoin".$thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false)."(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null, \$join_behavior = $join_behavior)
	{
		// we're going to modify criteria, so copy it first
		\$criteria = clone \$criteria;

		// We need to set the primary table name, since in the case that there are no WHERE columns
		// it will be impossible for the BasePeer::createSelectSql() method to determine which
		// tables go into the FROM clause.
		\$criteria->setPrimaryTableName(".$this->getPeerClassname()."::TABLE_NAME);

		if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
			\$criteria->setDistinct();
		}

		if (!\$criteria->hasSelectClause()) {
			".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		}
		
		\$criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count
		
		// Set the correct dbName
		\$criteria->setDbName(self::DATABASE_NAME);
		
		";
            $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);
         		
            // apply behaviors
            $this->applyBehaviorModifier('preSelect', $script);
            
            $script .= "
		\$stmt = ".$this->getPeerClassname()."::doCountStmt(\$criteria, \$con);

		if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$count = (int) \$row[0];
		} else {
			\$count = 0; // no rows returned; we infer that means 0 matches.
		}
		\$stmt->closeCursor();
		return \$count;
	}
";
					} // if fk table name != this table name
				} // if ! is reference only
			} // foreach column
		} // if count(fk) > 1

	} // addDoCountJoin()
	
	
	/**
	 * Adds the doSelectJoinAll() method.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoSelectJoinAll(&$script)
	{
		$table = $this->getTable();
		$className = $this->getObjectClassname();
		$join_behavior = $this->getJoinBehavior();

		$script .= "

	/**
	 * Selects a collection of $className objects pre-filled with all related objects.
	 *
	 * @param      Criteria  \$criteria
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     array Array of $className objects.
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 */
	public static function doSelectJoinAll(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
	{
		\$criteria = clone \$criteria;

		// Set the correct dbName if it has not been overridden
		if (\$criteria->getDbName() == Propel::getDefaultDB()) {
			\$criteria->setDbName(self::DATABASE_NAME);
		}

		".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol2 = (".$this->getPeerClassname()."::NUM_COLUMNS - ".$this->getPeerClassname()."::NUM_LAZY_LOAD_COLUMNS);
";
		$index = 2;
		foreach ($table->getForeignKeys() as $fk) {

			// Want to cover this case, but the code is not there yet.
			// Propel lacks a system for aliasing tables of the same name.
			if ( $fk->getForeignTableName() != $table->getName() ) {
				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());
				$new_index = $index + 1;

				$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
				$joinClassName = $joinedTablePeerBuilder->getObjectClassname();

				$script .= "
		".$joinedTablePeerBuilder->getPeerClassname()."::addSelectColumns(\$criteria);
		\$startcol$new_index = \$startcol$index + (".$joinedTablePeerBuilder->getPeerClassname()."::NUM_COLUMNS - ".$joinedTablePeerBuilder->getPeerClassname()."::NUM_LAZY_LOAD_COLUMNS);
";
				$index = $new_index;

			} // if fk->getForeignTableName != table->getName
		} // foreach [sub] foreign keys

		foreach ($table->getForeignKeys() as $fk) {
			// want to cover this case, but the code is not there yet.
			if ( $fk->getForeignTableName() != $table->getName() ) {
				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());
				$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
        $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);
			}
		}
		
		// apply behaviors
    $this->applyBehaviorModifier('preSelect', $script);
		
    $script .= "
		\$stmt = ".$this->getPeerClassname()."::doSelectStmt(\$criteria, \$con);
		\$results = array();

		while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$key1 = ".$this->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, 0);
			if (null !== (\$obj1 = ".$this->getPeerClassname()."::getInstanceFromPool(\$key1))) {
				// We no longer rehydrate the object, since this can cause data loss.
				// See http://propel.phpdb.org/trac/ticket/509
				// \$obj1->hydrate(\$row, 0, true); // rehydrate
			} else {";

		if ($table->getChildrenColumn()) {
			$script .= "
				\$omClass = ".$this->getPeerClassname()."::getOMClass(\$row, 0);
        \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
		} else {
			$script .= "
				\$cls = ".$this->getPeerClassname()."::getOMClass(false);
";
		}

		$script .= "
				" . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
				\$obj1->hydrate(\$row);
				".$this->getPeerClassname()."::addInstanceToPool(\$obj1, \$key1);
			} // if obj1 already loaded
";

		$index = 1;
		foreach ($table->getForeignKeys() as $fk ) {
			// want to cover this case, but the code is not there yet.
			// Why not? -because we'd have to alias the tables in the JOIN
			if ( $fk->getForeignTableName() != $table->getName() ) {
				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

				$thisTableObjectBuilder = $this->getNewObjectBuilder($table);
				$joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
				$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);


				$joinClassName = $joinedTableObjectBuilder->getObjectClassname();
				$interfaceName = $joinClassName;

				if ($joinTable->getInterface()) {
					$interfaceName = $this->prefixClassname($joinTable->getInterface());
				}

				$index++;

				$script .= "
			// Add objects for joined $joinClassName rows

			\$key$index = ".$joinedTablePeerBuilder->getPeerClassname()."::getPrimaryKeyHashFromRow(\$row, \$startcol$index);
			if (\$key$index !== null) {
				\$obj$index = ".$joinedTablePeerBuilder->getPeerClassname()."::getInstanceFromPool(\$key$index);
				if (!\$obj$index) {
";
				if ($joinTable->getChildrenColumn()) {
					$script .= "
					\$omClass = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(\$row, \$startcol$index);
          \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
				} else {
					$script .= "
					\$cls = ".$joinedTablePeerBuilder->getPeerClassname()."::getOMClass(false);
";
				} /* $joinTable->getChildrenColumn() */

				$script .= "
					" . $this->buildObjectInstanceCreationCode('$obj' . $index, '$cls') . "
					\$obj".$index."->hydrate(\$row, \$startcol$index);
					".$joinedTablePeerBuilder->getPeerClassname()."::addInstanceToPool(\$obj$index, \$key$index);
				} // if obj$index loaded

				// Add the \$obj1 (".$this->getObjectClassname().") to the collection in \$obj".$index." (".$joinedTablePeerBuilder->getObjectClassname().")";
				if ($fk->isLocalPrimaryKey()) {
					$script .= "
				\$obj1->set".$joinedTablePeerBuilder->getObjectClassname()."(\$obj".$index.");";
				} else {
					$script .= "
				\$obj".$index."->add".$joinedTableObjectBuilder->getRefFKPhpNameAffix($fk, $plural = false)."(\$obj1);";
				}
				$script .= "
			} // if joined row not null
";

			} // $fk->getForeignTableName() != $table->getName()
		} //foreach foreign key

		$script .= "
			\$results[] = \$obj1;
		}
		\$stmt->closeCursor();
		return \$results;
	}
";

	} // end addDoSelectJoinAll()

	
	/**
	 * Adds the doCountJoinAll() method.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoCountJoinAll(&$script)
	{
		$table = $this->getTable();
		$className = $this->getObjectClassname();
		$join_behavior = $this->getJoinBehavior();

		$script .= "

	/**
	 * Returns the number of rows matching criteria, joining all related tables
	 *
	 * @param      Criteria \$criteria
	 * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     int Number of matching rows.
	 */
	public static function doCountJoinAll(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null, \$join_behavior = $join_behavior)
	{
		// we're going to modify criteria, so copy it first
		\$criteria = clone \$criteria;

		// We need to set the primary table name, since in the case that there are no WHERE columns
		// it will be impossible for the BasePeer::createSelectSql() method to determine which
		// tables go into the FROM clause.
		\$criteria->setPrimaryTableName(".$this->getPeerClassname()."::TABLE_NAME);

		if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
			\$criteria->setDistinct();
		}

		if (!\$criteria->hasSelectClause()) {
			".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		}
		
		\$criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count
		
		// Set the correct dbName
		\$criteria->setDbName(self::DATABASE_NAME);
		
		";

		foreach ($table->getForeignKeys() as $fk) {
			// want to cover this case, but the code is not there yet.
			if ( $fk->getForeignTableName() != $table->getName() ) {
				$joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());
				$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
        $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);
			} // if fk->getForeignTableName != table->getName
		} // foreach [sub] foreign keys
		
		// apply behaviors
    $this->applyBehaviorModifier('preSelect', $script);
		
    $script .= "
		\$stmt = ".$this->getPeerClassname()."::doCountStmt(\$criteria, \$con);

		if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$count = (int) \$row[0];
		} else {
			\$count = 0; // no rows returned; we infer that means 0 matches.
		}
		\$stmt->closeCursor();
		return \$count;
	}";
	} // end addDoCountJoinAll()
	
	
	/**
	 * Adds the doCountJoinAllExcept*() methods.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addDoCountJoinAllExcept(&$script)
	{
		$table = $this->getTable();
		$join_behavior = $this->getJoinBehavior();

		$fkeys = $table->getForeignKeys();  // this sep assignment is necessary otherwise sub-loops over
		// getForeignKeys() will cause this to only execute one time.
		foreach ($fkeys as $fk ) {

			$tblFK = $table->getDatabase()->getTable($fk->getForeignTableName());

			$excludedTable = $table->getDatabase()->getTable($fk->getForeignTableName());

			$thisTableObjectBuilder = $this->getNewObjectBuilder($table);
			$excludedTableObjectBuilder = $this->getNewObjectBuilder($excludedTable);
			$excludedTablePeerBuilder = $this->getNewPeerBuilder($excludedTable);

			$excludedClassName = $excludedTableObjectBuilder->getObjectClassname();

			$script .= "

	/**
	 * Returns the number of rows matching criteria, joining the related ".$thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false)." table
	 *
	 * @param      Criteria \$criteria
	 * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
	 * @param      PropelPDO \$con
	 * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
	 * @return     int Number of matching rows.
	 */
	public static function doCountJoinAllExcept".$thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false)."(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null, \$join_behavior = $join_behavior)
	{
		// we're going to modify criteria, so copy it first
		\$criteria = clone \$criteria;

		// We need to set the primary table name, since in the case that there are no WHERE columns
		// it will be impossible for the BasePeer::createSelectSql() method to determine which
		// tables go into the FROM clause.
		\$criteria->setPrimaryTableName(".$this->getPeerClassname()."::TABLE_NAME);
		
		if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
			\$criteria->setDistinct();
		}

		if (!\$criteria->hasSelectClause()) {
			".$this->getPeerClassname()."::addSelectColumns(\$criteria);
		}
		
		\$criteria->clearOrderByColumns(); // ORDER BY should not affect count
		
		// Set the correct dbName
		\$criteria->setDbName(self::DATABASE_NAME);
		
		";

			foreach ($table->getForeignKeys() as $subfk) {
				// want to cover this case, but the code is not there yet.
				if ( $subfk->getForeignTableName() != $table->getName() ) {
					$joinTable = $table->getDatabase()->getTable($subfk->getForeignTableName());
					$joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
					$joinClassName = $joinedTablePeerBuilder->getObjectClassname();

					if ($joinClassName != $excludedClassName)
					{
            $script .= $this->addCriteriaJoin($subfk, $table, $joinTable, $joinedTablePeerBuilder);
					}
				}
			} // foreach fkeys
			
			// apply behaviors
      $this->applyBehaviorModifier('preSelect', $script);
			
      $script .= "
		\$stmt = ".$this->getPeerClassname()."::doCountStmt(\$criteria, \$con);

		if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
			\$count = (int) \$row[0];
		} else {
			\$count = 0; // no rows returned; we infer that means 0 matches.
		}
		\$stmt->closeCursor();
		return \$count;
	}
";
		} // foreach fk

	} // addDoCountJoinAllExcept

	/**
	 * Adds the retrieveByPK method for tables with single-column primary key.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addRetrieveByPK_SinglePK(&$script)
	{
		$table = $this->getTable();
		$pks = $table->getPrimaryKey();
		$col = $pks[0];

		$script .= "
	/**
	 * Retrieve a single object by pkey.
	 *
	 * @param      ".$col->getPhpType()." \$pk the primary key.
	 * @param      PropelPDO \$con the connection to use
	 * @return     " .$this->getObjectClassname(). "
	 */
	public static function ".$this->getRetrieveMethodName()."(\$pk, PropelPDO \$con = null)
	{

		if (null !== (\$obj = ".$this->getPeerClassname()."::getInstanceFromPool(".$this->getInstancePoolKeySnippet('$pk')."))) {
			return \$obj;
		}

		\$criteria = new Criteria(".$this->getPeerClassname()."::DATABASE_NAME);
		\$criteria->add(".$this->getColumnConstant($col).", \$pk);

		\$v = ".$this->getPeerClassname()."::doSelect(\$criteria, \$con);

		return !empty(\$v) > 0 ? \$v[0] : null;
	}
";
	}

	/**
	 * Adds the retrieveByPKs method for tables with single-column primary key.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addRetrieveByPKs_SinglePK(&$script)
	{
		$table = $this->getTable();
		$script .= "
	/**
	 * Retrieve multiple objects by pkey.
	 *
	 * @param      array \$pks List of primary keys
	 * @param      PropelPDO \$con the connection to use
	 * @throws     PropelException Any exceptions caught during processing will be
	 *		 rethrown wrapped into a PropelException.
	 */
	public static function ".$this->getRetrieveMethodName()."s(\$pks, PropelPDO \$con = null)
	{
		\$objs = null;
		if (empty(\$pks)) {
			\$objs = array();
		} else {
			\$criteria = new Criteria(".$this->getPeerClassname()."::DATABASE_NAME);";
		$k1 = $table->getPrimaryKey();
		$script .= "
			\$criteria->add(".$this->getColumnConstant($k1[0]).", \$pks, Criteria::IN);";
		$script .= "
			\$objs = ".$this->getPeerClassname()."::doSelect(\$criteria, \$con);
		}
		return \$objs;
	}
";
	}

	/**
	 * Adds the retrieveByPK method for tables with multi-column primary key.
	 * @param      string &$script The script will be modified in this method.
	 */
	protected function addRetrieveByPK_MultiPK(&$script)
	{
		$table = $this->getTable();
		$script .= "
	/**
	 * Retrieve object using using composite pkey values.";
		foreach ($table->getPrimaryKey() as $col) {
			$clo = strtolower($col->getName());
			$cptype = $col->getPhpType();
			$script .= "
	 * @param      $cptype $".$clo;
		}
		$script .= "
	 * @param      PropelPDO \$con
	 * @return     ".$this->getObjectClassname()."
	 */
	public static function ".$this->getRetrieveMethodName()."(";

		$php = array();
		foreach ($table->getPrimaryKey() as $col) {
			$clo = strtolower($col->getName());
			$php[] = '$' . $clo;
		} /* foreach */

		$script .= implode(', ', $php);

		$script .= ", PropelPDO \$con = null) {
		\$key = ".$this->getInstancePoolKeySnippet($php).";";
 		$script .= "
 		if (null !== (\$obj = ".$this->getPeerClassname()."::getInstanceFromPool(\$key))) {
 			return \$obj;
		}

		\$criteria = new Criteria(".$this->getPeerClassname()."::DATABASE_NAME);";
		foreach ($table->getPrimaryKey() as $col) {
			$clo = strtolower($col->getName());
			$script .= "
		\$criteria->add(".$this->getColumnConstant($col).", $".$clo.");";
		}
		$script .= "
		\$v = ".$this->getPeerClassname()."::doSelect(\$criteria, \$con);

		return !empty(\$v) ? \$v[0] : null;
	}";
	}
	
}
