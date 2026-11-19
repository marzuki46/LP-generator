/**
 * Behavioral regression for the admin "Mulai Generate" button.
 *
 * Run with: node tests/test-admin-runner.js
 * It executes the real admin.js with a tiny jQuery stub and asserts the AJAX
 * payload. A previous `var data = data || {};` made `data.lpgen = ...` throw
 * because `var` hoisting left `data` undefined.
 */
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const source = fs.readFileSync( path.resolve( __dirname, '..', 'admin', 'js', 'admin.js' ), 'utf8' );
const requests = [];
let clickHandler = null;
let disabled = false;

function chain( overrides = {} ) {
	const item = { length: 1 };
	for ( const method of [ 'on', 'each', 'show', 'hide', 'css', 'text', 'html', 'prop', 'append', 'prepend', 'val', 'addClass', 'removeClass', 'attr', 'find', 'trigger', 'slideDown', 'slideUp', 'focus', 'first', 'is' ] ) {
		item[ method ] = () => item;
	}
	return Object.assign( item, overrides );
}

const field = {};
const formSelector = 'input[name^="lpgen["], select[name^="lpgen["], textarea[name^="lpgen["]';
const doc = {};

const button = {
	data: ( key ) => ( 'lpgen-action' === key ? 'start-campaign' : 'campaign' === key ? 42 : undefined ),
	text: ( value ) => ( undefined === value ? 'Mulai Generate' : button ),
	prop: ( key, value ) => {
		if ( 'disabled' === key ) {
			if ( undefined !== value ) { disabled = value; }
			return undefined === value ? disabled : button;
		}
		return button;
	},
	removeClass: () => button,
	addClass: () => button,
};

function jq( selector ) {
	if ( selector === doc ) {
		return chain( { on: ( event, target, handler ) => {
			if ( '[data-lpgen-action]' === target ) { clickHandler = handler; }
			return chain();
		} } );
	}
	if ( selector === button ) { return button; }
	if ( selector === field ) {
		return chain( {
			attr: () => 'lpgen[offer_name]',
			is: () => false,
			val: () => 'Jasa Website Solo',
		} );
	}
	if ( typeof selector === 'string' && selector === formSelector ) {
		return chain( { each: ( callback ) => { callback.call( field ); return chain(); } } );
	}
	if ( typeof selector === 'string' && selector === '#title' ) { return chain( { val: () => '' } ); }
	return chain();
}

jq.extend = Object.assign;
jq.trim = ( value ) => String( value || '' ).trim();
jq.isArray = Array.isArray;
jq.ajax = ( options ) => {
	requests.push( options.data );
	const response = 'lpgen_start_campaign' === options.data.action
		? { ok: true, jobs: 2, message: 'antrean dibuat' }
		: { ok: true, done: true, remaining: 0, failed: 0, message: 'selesai' };
	const promise = {
		done: ( handler ) => { handler( response ); return promise; },
		fail: () => promise,
		always: () => promise,
	};
	return promise;
};

global.window = {
	lpgenAdmin: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'nonce', i18n: {} },
	confirm: () => true,
	setTimeout: () => 0,
	clearTimeout: () => {},
	setInterval: () => 1,
	clearInterval: () => {},
	location: { reload: () => {} },
};
global.jQuery = jq;
global.document = doc;

try {
	new Function( source )();
	assert.ok( clickHandler, 'handler tombol tidak terdaftar' );
	clickHandler.call( button, { preventDefault() {} } );
	const start = requests.find( ( payload ) => 'lpgen_start_campaign' === payload.action );
	assert.ok( start, 'permintaan start-campaign tidak dikirim' );
	assert.strictEqual( 42, start.campaign, 'campaign ikut dikirim' );
	assert.strictEqual( 'nonce', start.nonce, 'nonce ikut dikirim' );
	assert.deepStrictEqual( { offer_name: 'Jasa Website Solo' }, start.lpgen, 'nilai form dikirim di payload lpgen' );
	console.log( 'PASS  tombol Mulai Generate mengirim payload lpgen tanpa exception' );
} catch ( error ) {
	console.error( 'FAIL  ' + ( error && error.message ? error.message : error ) );
	process.exitCode = 1;
}
