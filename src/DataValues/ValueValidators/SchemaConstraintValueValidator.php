<?php

namespace SMW\DataValues\ValueValidators;

use SMWDataValue as DataValue;
use SMWDataItem as DataItem;
use SMW\PropertySpecificationLookup;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\Schema\SchemaFactory;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 3.1
 *
 * @author mwjames
 */
class SchemaConstraintValueValidator implements ConstraintValueValidator {

	/**
	 * @var SchemaFactory
	 */
	private $schemaFactory;

	/**
	 * @var PropertySpecificationLookup
	 */
	private $propertySpecificationLookup;

	/**
	 * @var boolean
	 */
	private $hasConstraintViolation = false;

	/**
	 * @since 3.1
	 *
	 * @param SchemaFactory $schemaFactory
	 * @param PropertySpecificationLookup $propertySpecificationLookup
	 */
	public function __construct( SchemaFactory $schemaFactory, PropertySpecificationLookup $propertySpecificationLookup ) {
		$this->schemaFactory = $schemaFactory;
		$this->propertySpecificationLookup = $propertySpecificationLookup;
	}

	/**
	 * @since 3.1
	 *
	 * {@inheritDoc}
	 */
	public function hasConstraintViolation() {
		return $this->hasConstraintViolation;
	}

	/**
	 * @since 3.1
	 *
	 * {@inheritDoc}
	 */
	public function validate( $dataValue ) {

		$this->hasConstraintViolation = false;

		if ( $dataValue->getProperty() === null ) {
			return;
		}

		$dataItems = $this->propertySpecificationLookup->getSpecification(
			$dataValue->getProperty(),
			new DIProperty( '_CONSTRAINT_SCHEMA' )
		);

		if ( $dataItems === [] || $dataItems === null ) {
			return;
		}

		$schema_page = end( $dataItems );

		if ( !$schema_page instanceof DIWikiPage ) {
			return;
		}

		$dataItems = $this->propertySpecificationLookup->getSpecification(
			$schema_page,
			new DIProperty( '_SCHEMA_DEF' )
		);

		if ( $dataItems === [] || $dataItems === null ) {
			return;
		}

		foreach ( $dataItems as $dataItem ) {

			$schema = $this->schemaFactory->newSchema(
				$schema_page->getDBKey(),
				$dataItem->getString()
			);

			$this->checkSchema( $dataValue, $schema );
		}
	}

	private function checkSchema( $dataValue, $schema ) {
		if ( ( $allowed_namespaces = $schema->get( 'constraints.annotation_constraints.allowed_namespaces' ) ) !== null ) {
			$this->checkAllowedNamespaces( $dataValue, $allowed_namespaces );
		}
	}

	private function checkAllowedNamespaces( $dataValue, $namespaces ) {

		$dataItem = $dataValue->getDataItem();

		if ( $dataItem->getDIType() !== DataItem::TYPE_WIKIPAGE ) {
			$this->hasConstraintViolation = true;

			return $dataValue->addErrorMsg(
				[
					'smw-datavalue-constraint-schema-violation-allowed-namespace-no-page-type'
				]
			);
		}

		foreach ( $namespaces as $ns ) {
			if ( defined( $ns ) && constant( $ns ) == $dataItem->getNamespace() ) {
				return;
			}
		}

		$this->hasConstraintViolation = true;

		$dataValue->addErrorMsg(
			[
				'smw-datavalue-constraint-schema-violation-allowed-namespace-no-match',
				$dataValue->getWikiValue(),
				implode(', ', $namespaces ),
				$dataValue->getProperty()->getLabel()
			]
		);
	}

}
