// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'owner_staff_pay_api.dart';

// dart format off

// **************************************************************************
// RetrofitGenerator
// **************************************************************************

// ignore_for_file: type=lint
// ignore_for_file: unnecessary_brace_in_string_interps,no_leading_underscores_for_local_identifiers,unused_element,unnecessary_string_interpolations,unused_element_parameter,avoid_unused_constructor_parameters,unreachable_from_main,avoid_redundant_argument_values

class _OwnerStaffPayApi implements OwnerStaffPayApi {
  _OwnerStaffPayApi(this._dio, {this.baseUrl, this.errorLogger});

  final Dio _dio;

  String? baseUrl;

  final ParseErrorLogger? errorLogger;

  @override
  Future<GetV1OwnerStaffPayRulesResponse> staffPayRuleIndex({
    int? branchId,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'branch_id': branchId};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerStaffPayRulesResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/rules',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerStaffPayRulesResponse _value;
    try {
      _value = GetV1OwnerStaffPayRulesResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerStaffPayRulesResponse> staffPayRuleStore({
    required V1OwnerStaffPayRulesRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options = _setStreamType<PostV1OwnerStaffPayRulesResponse>(
      Options(method: 'POST', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/rules',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerStaffPayRulesResponse _value;
    try {
      _value = PostV1OwnerStaffPayRulesResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PutV1OwnerStaffPayRulesUuidResponse> staffPayRuleUpdate({
    required String uuid,
    V1OwnerStaffPayRulesUuidRequestBody? body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body?.toJson() ?? <String, dynamic>{});
    final _options = _setStreamType<PutV1OwnerStaffPayRulesUuidResponse>(
      Options(method: 'PUT', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/rules/${uuid}',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PutV1OwnerStaffPayRulesUuidResponse _value;
    try {
      _value = PutV1OwnerStaffPayRulesUuidResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<DeleteV1OwnerStaffPayRulesUuidResponse> staffPayRuleDestroy({
    required String uuid,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<DeleteV1OwnerStaffPayRulesUuidResponse>(
      Options(method: 'DELETE', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/rules/${uuid}',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late DeleteV1OwnerStaffPayRulesUuidResponse _value;
    try {
      _value = DeleteV1OwnerStaffPayRulesUuidResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PutV1OwnerStaffPaySettingsResponse> staffPayRuleSettings({
    required V1OwnerStaffPaySettingsRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options = _setStreamType<PutV1OwnerStaffPaySettingsResponse>(
      Options(method: 'PUT', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/settings',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PutV1OwnerStaffPaySettingsResponse _value;
    try {
      _value = PutV1OwnerStaffPaySettingsResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1OwnerStaffPayStatementsResponse> staffPayStatementIndex({
    int? branchId,
    String? from,
    String? to,
    String? status,
    int? limit,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{
      r'branch_id': branchId,
      r'from': from,
      r'to': to,
      r'status': status,
      r'limit': limit,
    };
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerStaffPayStatementsResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/statements',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerStaffPayStatementsResponse _value;
    try {
      _value = GetV1OwnerStaffPayStatementsResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerStaffPayStatementsResponse> staffPayStatementStore({
    String? idempotencyKey,
    V1OwnerStaffPayStatementsRequestBody? body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{r'Idempotency-Key': idempotencyKey};
    _headers.removeWhere((k, v) => v == null);
    final _data = <String, dynamic>{};
    _data.addAll(body?.toJson() ?? <String, dynamic>{});
    final _options = _setStreamType<PostV1OwnerStaffPayStatementsResponse>(
      Options(method: 'POST', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/statements',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerStaffPayStatementsResponse _value;
    try {
      _value = PostV1OwnerStaffPayStatementsResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1OwnerStaffPayStatementsUuidResponse> staffPayStatementShow({
    required String uuid,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerStaffPayStatementsUuidResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/statements/${uuid}',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerStaffPayStatementsUuidResponse _value;
    try {
      _value = GetV1OwnerStaffPayStatementsUuidResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<DeleteV1OwnerStaffPayStatementsUuidResponse> staffPayStatementDestroy({
    required String uuid,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<DeleteV1OwnerStaffPayStatementsUuidResponse>(
          Options(method: 'DELETE', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/staff-pay/statements/${uuid}',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late DeleteV1OwnerStaffPayStatementsUuidResponse _value;
    try {
      _value = DeleteV1OwnerStaffPayStatementsUuidResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerStaffPayStatementsUuidCloseResponse>
  staffPayStatementClose({required String uuid}) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<PostV1OwnerStaffPayStatementsUuidCloseResponse>(
          Options(method: 'POST', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/staff-pay/statements/${uuid}/close',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerStaffPayStatementsUuidCloseResponse _value;
    try {
      _value = PostV1OwnerStaffPayStatementsUuidCloseResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PutV1OwnerStaffPayStatementsUuidLinesLineResponse>
  staffPayStatementAdjust({
    required String uuid,
    required String line,
    required V1OwnerStaffPayStatementsUuidLinesLineRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options =
        _setStreamType<PutV1OwnerStaffPayStatementsUuidLinesLineResponse>(
          Options(method: 'PUT', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/staff-pay/statements/${uuid}/lines/${line}',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PutV1OwnerStaffPayStatementsUuidLinesLineResponse _value;
    try {
      _value = PutV1OwnerStaffPayStatementsUuidLinesLineResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse>
  staffPayStatementPay({
    required String uuid,
    required String line,
    required String idempotencyKey,
    required V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{r'Idempotency-Key': idempotencyKey};
    _headers.removeWhere((k, v) => v == null);
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options =
        _setStreamType<PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse>(
          Options(method: 'POST', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/staff-pay/statements/${uuid}/lines/${line}/pay',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse _value;
    try {
      _value = PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerStaffPayStatementsUuidRecomputeResponse>
  staffPayStatementRecompute({required String uuid}) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<PostV1OwnerStaffPayStatementsUuidRecomputeResponse>(
          Options(method: 'POST', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/staff-pay/statements/${uuid}/recompute',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerStaffPayStatementsUuidRecomputeResponse _value;
    try {
      _value = PostV1OwnerStaffPayStatementsUuidRecomputeResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerStaffPayStatementsUuidReopenResponse>
  staffPayStatementReopen({
    required String uuid,
    required V1OwnerStaffPayStatementsUuidReopenRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options =
        _setStreamType<PostV1OwnerStaffPayStatementsUuidReopenResponse>(
          Options(method: 'POST', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/staff-pay/statements/${uuid}/reopen',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerStaffPayStatementsUuidReopenResponse _value;
    try {
      _value = PostV1OwnerStaffPayStatementsUuidReopenResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1OwnerStaffPayWorkSessionsResponse> staffWorkSessionIndex({
    int? branchId,
    String? from,
    String? to,
    int? userId,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{
      r'branch_id': branchId,
      r'from': from,
      r'to': to,
      r'user_id': userId,
    };
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerStaffPayWorkSessionsResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/work-sessions',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerStaffPayWorkSessionsResponse _value;
    try {
      _value = GetV1OwnerStaffPayWorkSessionsResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerStaffPayWorkSessionsResponse> staffWorkSessionStore({
    required V1OwnerStaffPayWorkSessionsRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options = _setStreamType<PostV1OwnerStaffPayWorkSessionsResponse>(
      Options(method: 'POST', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/work-sessions',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerStaffPayWorkSessionsResponse _value;
    try {
      _value = PostV1OwnerStaffPayWorkSessionsResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PutV1OwnerStaffPayWorkSessionsUuidResponse> staffWorkSessionUpdate({
    required String uuid,
    required V1OwnerStaffPayWorkSessionsUuidRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options = _setStreamType<PutV1OwnerStaffPayWorkSessionsUuidResponse>(
      Options(method: 'PUT', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/staff-pay/work-sessions/${uuid}',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PutV1OwnerStaffPayWorkSessionsUuidResponse _value;
    try {
      _value = PutV1OwnerStaffPayWorkSessionsUuidResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<DeleteV1OwnerStaffPayWorkSessionsUuidResponse>
  staffWorkSessionDestroy({
    required String uuid,
    required String reason,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'reason': reason};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<DeleteV1OwnerStaffPayWorkSessionsUuidResponse>(
          Options(method: 'DELETE', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/staff-pay/work-sessions/${uuid}',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late DeleteV1OwnerStaffPayWorkSessionsUuidResponse _value;
    try {
      _value = DeleteV1OwnerStaffPayWorkSessionsUuidResponse.fromJson(
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
