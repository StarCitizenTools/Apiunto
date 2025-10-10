local Apiunto = {}
local php

function Apiunto.get( source, uri, args )
    if source == nil or type( source ) ~= 'string' or source == '' then
        error( 'Source name is missing or invalid.', 2 )
    end
    if uri == nil or type( uri ) ~= 'string' then
        error( 'Uri is missing or not a string.', 2 )
    end
    if args ~= nil and type( args ) ~= 'table' then
        error( 'Args must be a table or nil.', 2)
    end

    return php.get_raw( source, uri, args or {} )
end

function Apiunto.setupInterface( options )
	-- Boilerplate
	Apiunto.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- Register this library in the "mw" global
	mw = mw or {}
	mw.ext = mw.ext or {}
	mw.ext.Apiunto = Apiunto

	package.loaded['mw.ext.Apiunto'] = Apiunto
end

return Apiunto
