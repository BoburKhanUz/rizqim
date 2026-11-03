// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'owner_packaging_api.dart';

// dart format off

// **************************************************************************
// RetrofitGenerator
// **************************************************************************

// ignore_for_file: type=lint
// ignore_for_file: unnecessary_brace_in_string_interps,no_leading_underscores_for_local_identifiers,unused_element,unnecessary_string_interpolations,unused_element_parameter,avoid_unused_constructor_parameters,unreachable_from_main,avoid_redundant_argument_values

class _OwnerPackagingApi implements OwnerPackagingApi {
  _OwnerPackagingApi(this._dio, {this.baseUrl, this.errorLogger});

  final Dio _dio;

  String? baseUrl;

  final ParseErrorLogger? errorLogger;

  @override
  Future<GetV1OwnerBranchesBranchBusinessDaysResponse>
  branchBusinessDayReportIndex({
    required String branch,
    String? from,
    String? to,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'from': from, r'to': to};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<GetV1OwnerBranchesBranchBusinessDaysResponse>(
          Options(method: 'GET', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/branches/${branch}/business-days',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerBranchesBranchBusinessDaysResponse _value;
    try {
      _value = GetV1OwnerBranchesBranchBusinessDaysResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1OwnerBranchesBranchPackagingRulesResponse>
  branchPackagingRulesIndex({required String branch}) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<GetV1OwnerBranchesBranchPackagingRulesResponse>(
          Options(method: 'GET', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/branches/${branch}/packaging-rules',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerBranchesBranchPackagingRulesResponse _value;
    try {
      _value = GetV1OwnerBranchesBranchPackagingRulesResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerBranchesBranchPackagingRulesResponse>
  branchPackagingRulesStore({required String branch}) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<PostV1OwnerBranchesBranchPackagingRulesResponse>(
          Options(method: 'POST', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/branches/${branch}/packaging-rules',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerBranchesBranchPackagingRulesResponse _value;
    try {
      _value = PostV1OwnerBranchesBranchPackagingRulesResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PutV1OwnerBranchesBranchPackagingRulesRuleResponse>
  branchPackagingRulesUpdate({
    required String branch,
    required String rule,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<PutV1OwnerBranchesBranchPackagingRulesRuleResponse>(
          Options(method: 'PUT', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/branches/${branch}/packaging-rules/${rule}',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PutV1OwnerBranchesBranchPackagingRulesRuleResponse _value;
    try {
      _value = PutV1OwnerBranchesBranchPackagingRulesRuleResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse>
  branchPackagingRulesDestroy({
    required String branch,
    required String rule,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse>(
          Options(method: 'DELETE', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/branches/${branch}/packaging-rules/${rule}',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse _value;
    try {
      _value = DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1OwnerBranchesBranchPickupSettingsResponse>
  branchPickupSettingsShow({required String branch}) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options =
        _setStreamType<GetV1OwnerBranchesBranchPickupSettingsResponse>(
          Options(method: 'GET', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/branches/${branch}/pickup-settings',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerBranchesBranchPickupSettingsResponse _value;
    try {
      _value = GetV1OwnerBranchesBranchPickupSettingsResponse.fromJson(
        _result.data!,
      );
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PutV1OwnerBranchesBranchPickupSettingsResponse>
  branchPickupSettingsUpdate({
    required String branch,
    V1OwnerBranchesBranchPickupSettingsRequestBody? body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body?.toJson() ?? <String, dynamic>{});
    final _options =
        _setStreamType<PutV1OwnerBranchesBranchPickupSettingsResponse>(
          Options(method: 'PUT', headers: _headers, extra: _extra)
              .compose(
                _dio.options,
                '/v1/owner/branches/${branch}/pickup-settings',
                queryParameters: queryParameters,
                data: _data,
              )
              .copyWith(
                baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl),
              ),
        );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PutV1OwnerBranchesBranchPickupSettingsResponse _value;
    try {
      _value = PutV1OwnerBranchesBranchPickupSettingsResponse.fromJson(
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
