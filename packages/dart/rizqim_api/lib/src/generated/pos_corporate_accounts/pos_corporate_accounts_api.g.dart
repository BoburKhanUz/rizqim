// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pos_corporate_accounts_api.dart';

// dart format off

// **************************************************************************
// RetrofitGenerator
// **************************************************************************

// ignore_for_file: type=lint
// ignore_for_file: unnecessary_brace_in_string_interps,no_leading_underscores_for_local_identifiers,unused_element,unnecessary_string_interpolations,unused_element_parameter,avoid_unused_constructor_parameters,unreachable_from_main,avoid_redundant_argument_values

class _PosCorporateAccountsApi implements PosCorporateAccountsApi {
  _PosCorporateAccountsApi(this._dio, {this.baseUrl, this.errorLogger});

  final Dio _dio;

  String? baseUrl;

  final ParseErrorLogger? errorLogger;

  @override
  Future<GetV1PosCustomerAccountsMembersLookupResponse> corporateAccountLookup({
    String? code,
    String? phone,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'code': code, r'phone': phone};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<GetV1PosCustomerAccountsMembersLookupResponse>(
          Options(method: 'GET', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/pos/customer-accounts/members/lookup',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1PosCustomerAccountsMembersLookupResponse _value;
    try {
      _value = GetV1PosCustomerAccountsMembersLookupResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1PosCustomerAccountsUuidCorporateStatementResponse>
  corporateAccountStatement({required String uuid, String? month}) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'month': month};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<GetV1PosCustomerAccountsUuidCorporateStatementResponse>(
          Options(method: 'GET', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/pos/customer-accounts/${uuid}/corporate-statement',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1PosCustomerAccountsUuidCorporateStatementResponse _value;
    try {
      _value = GetV1PosCustomerAccountsUuidCorporateStatementResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Stream<String> corporateAccountExport({
    required String uuid,
    String? month,
  }) async* {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'month': month};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<String>(
      Options(
            method: 'GET',
            headers: _headers,
            extra: _extra,
            responseType: ResponseType.stream,
          )
          .compose(
            _dio.options,
            '/v1/pos/customer-accounts/${uuid}/corporate-statement/export',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = _dio.fetch<ResponseBody>(_options);
    final _value = _result.asStream().asyncExpand(
      (response) => utf8.decoder.bind(response.data!.stream),
    );
    yield* _value;
  }

  @override
  Future<GetV1PosCustomerAccountsUuidMembersResponse> corporateAccountMembers({
    required String uuid,
    String? q,
    bool? active,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'q': q, r'active': active};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<GetV1PosCustomerAccountsUuidMembersResponse>(
          Options(method: 'GET', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/pos/customer-accounts/${uuid}/members',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1PosCustomerAccountsUuidMembersResponse _value;
    try {
      _value = GetV1PosCustomerAccountsUuidMembersResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1PosCustomerAccountsUuidMembersResponse>
  corporateAccountStoreMember({
    required String uuid,
    required V1PosCustomerAccountsUuidMembersRequestBody body,
    String? idempotencyKey,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{r'Idempotency-Key': idempotencyKey};
    _headers.removeWhere((k, v) => v == null);
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options =
        _setStreamType<PostV1PosCustomerAccountsUuidMembersResponse>(
          Options(method: 'POST', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/pos/customer-accounts/${uuid}/members',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1PosCustomerAccountsUuidMembersResponse _value;
    try {
      _value = PostV1PosCustomerAccountsUuidMembersResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1PosCustomerAccountsUuidMembersBulkResponse>
  corporateAccountBulk({
    required String uuid,
    required V1PosCustomerAccountsUuidMembersBulkRequestBody body,
    String? idempotencyKey,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{r'Idempotency-Key': idempotencyKey};
    _headers.removeWhere((k, v) => v == null);
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options =
        _setStreamType<PostV1PosCustomerAccountsUuidMembersBulkResponse>(
          Options(method: 'POST', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/pos/customer-accounts/${uuid}/members/bulk',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1PosCustomerAccountsUuidMembersBulkResponse _value;
    try {
      _value = PostV1PosCustomerAccountsUuidMembersBulkResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PatchV1PosCustomerAccountsUuidMembersMemberResponse>
  corporateAccountUpdateMember({
    required String uuid,
    required String member,
    String? idempotencyKey,
    V1PosCustomerAccountsUuidMembersMemberRequestBody? body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{r'Idempotency-Key': idempotencyKey};
    _headers.removeWhere((k, v) => v == null);
    final _data = <String, dynamic>{};
    _data.addAll(body?.toJson() ?? <String, dynamic>{});
    final _options =
        _setStreamType<PatchV1PosCustomerAccountsUuidMembersMemberResponse>(
          Options(method: 'PATCH', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/pos/customer-accounts/${uuid}/members/${member}',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PatchV1PosCustomerAccountsUuidMembersMemberResponse _value;
    try {
      _value = PatchV1PosCustomerAccountsUuidMembersMemberResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  RequestOptions _setStreamType<T>(RequestOptions requestOptions) {
    if (T != dynamic &&
        !(requestOptions.responseType == ResponseType.bytes ||
            requestOptions.responseType == ResponseType.stream)) {
      if (T == String) {
        requestOptions.responseType = ResponseType.plain;
      } else {
        requestOptions.responseType = ResponseType.json;
      }
    }
    return requestOptions;
  }

  String _combineBaseUrls(String dioBaseUrl, String? baseUrl) {
    if (baseUrl == null || baseUrl.trim().isEmpty) {
      return dioBaseUrl;
    }

    final url = Uri.parse(baseUrl);

    if (url.isAbsolute) {
      return url.toString();
    }

    return Uri.parse(dioBaseUrl).resolveUri(url).toString();
  }
}

// dart format on
