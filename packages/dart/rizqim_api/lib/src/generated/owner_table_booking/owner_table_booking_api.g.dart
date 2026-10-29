// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'owner_table_booking_api.dart';

// dart format off

// **************************************************************************
// RetrofitGenerator
// **************************************************************************

// ignore_for_file: type=lint
// ignore_for_file: unnecessary_brace_in_string_interps,no_leading_underscores_for_local_identifiers,unused_element,unnecessary_string_interpolations,unused_element_parameter,avoid_unused_constructor_parameters,unreachable_from_main,avoid_redundant_argument_values

class _OwnerTableBookingApi implements OwnerTableBookingApi {
  _OwnerTableBookingApi(this._dio, {this.baseUrl, this.errorLogger});

  final Dio _dio;

  String? baseUrl;

  final ParseErrorLogger? errorLogger;

  @override
  Future<GetV1OwnerBranchesIdBookingRulesResponse> branchBookingRulesShow({
    required int id,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerBranchesIdBookingRulesResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/branches/${id}/booking-rules',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerBranchesIdBookingRulesResponse _value;
    try {
      _value = GetV1OwnerBranchesIdBookingRulesResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PutV1OwnerBranchesIdBookingRulesResponse> branchBookingRulesUpdate({
    required int id,
    V1OwnerBranchesIdBookingRulesRequestBody? body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body?.toJson() ?? <String, dynamic>{});
    final _options = _setStreamType<PutV1OwnerBranchesIdBookingRulesResponse>(
      Options(method: 'PUT', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/branches/${id}/booking-rules',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PutV1OwnerBranchesIdBookingRulesResponse _value;
    try {
      _value = PutV1OwnerBranchesIdBookingRulesResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1OwnerWaitlistResponse> ownerWaitlistIndex({
    int? branchId,
    String? from,
    String? to,
    String? status,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{
      r'branch_id': branchId,
      r'from': from,
      r'to': to,
      r'status': status,
    };
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerWaitlistResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/waitlist',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerWaitlistResponse _value;
    try {
      _value = GetV1OwnerWaitlistResponse.fromJson(_result.data!);
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
