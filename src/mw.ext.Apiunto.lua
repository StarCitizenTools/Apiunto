local Apiunto = {}
local php


local function request( payload )
    if payload.uri == nil then
        error( 'Uri is missing in payload.', 3 )
    end

    if type( payload.args ) ~= 'table' then
        payload.args = {}
    end

    --return payload.uri
    return php.get_raw( payload.uri, payload.args )
end

function Apiunto.get_raw( uri, args )
    return request( {
        uri = uri,
        args = args,
    } )
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
