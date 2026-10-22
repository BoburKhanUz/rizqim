// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'owner_catalog_api.dart';

// dart format off

// **************************************************************************
// RetrofitGenerator
// **************************************************************************

// ignore_for_file: type=lint
// ignore_for_file: unnecessary_brace_in_string_interps,no_leading_underscores_for_local_identifiers,unused_element,unnecessary_string_interpolations,unused_element_parameter,avoid_unused_constructor_parameters,unreachable_from_main,avoid_redundant_argument_values

class _OwnerCatalogApi implements OwnerCatalogApi {
  _OwnerCatalogApi(this._dio, {this.baseUrl, this.errorLogger});

  final Dio _dio;

  String? baseUrl;

  final ParseErrorLogger? errorLogger;

  @override
  Future<GetV1OwnerCategoriesResponse> ownerCatalogGetCategories() async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerCategoriesResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/categories',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerCategoriesResponse _value;
    try {
      _value = GetV1OwnerCategoriesResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerCategoriesResponse> ownerCatalogStoreCategory({
    required V1OwnerCategoriesRequestBody body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body.toJson());
    final _options = _setStreamType<PostV1OwnerCategoriesResponse>(
      Options(method: 'POST', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/categories',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerCategoriesResponse _value;
    try {
      _value = PostV1OwnerCategoriesResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<GetV1OwnerProductsResponse> ownerCatalogGetProducts() async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<GetV1OwnerProductsResponse>(
      Options(method: 'GET', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/products',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late GetV1OwnerProductsResponse _value;
    try {
      _value = GetV1OwnerProductsResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PostV1OwnerProductsResponse> ownerCatalogStoreProduct({
    required String name,
    required int categoryId,
    required String variants,
    String? description,
    bool? isActive,
    File? image,
    bool? batchTracked,
    bool? quickAdd,
    int? sortOrder,
    String? kind,
    String? mxikCode,
    String? packageCode,
    num? vatRate,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    final _data = FormData();
    _data.fields.add(MapEntry('name', name));
    _data.fields.add(MapEntry('category_id', categoryId.toString()));
    _data.fields.add(MapEntry('variants', variants));
    if (description != null) {
      _data.fields.add(MapEntry('description', description));
    }
    if (isActive != null) {
      _data.fields.add(MapEntry('is_active', isActive.toString()));
    }
    if (image != null) {
      _data.files.add(
        MapEntry(
          'image',
          MultipartFile.fromFileSync(
            image.path,
            filename: image.path.split(Platform.pathSeparator).last,
          ),
        ),
      );
    }
    if (batchTracked != null) {
      _data.fields.add(MapEntry('batch_tracked', batchTracked.toString()));
    }
    if (quickAdd != null) {
      _data.fields.add(MapEntry('quick_add', quickAdd.toString()));
    }
    if (sortOrder != null) {
      _data.fields.add(MapEntry('sort_order', sortOrder.toString()));
    }
    if (kind != null) {
      _data.fields.add(MapEntry('kind', kind));
    }
    if (mxikCode != null) {
      _data.fields.add(MapEntry('mxik_code', mxikCode));
    }
    if (packageCode != null) {
      _data.fields.add(MapEntry('package_code', packageCode));
    }
    if (vatRate != null) {
      _data.fields.add(MapEntry('vat_rate', vatRate.toString()));
    }
    final _options = _setStreamType<PostV1OwnerProductsResponse>(
      Options(
            method: 'POST',
            headers: _headers,
            extra: _extra,
            contentType: 'multipart/form-data',
          )
          .compose(
            _dio.options,
            '/v1/owner/products',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PostV1OwnerProductsResponse _value;
    try {
      _value = PostV1OwnerProductsResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<dynamic> ownerCatalogUpdateProduct({
    required String id,
    required String name,
    required int categoryId,
    required String variants,
    int? toggleActive,
    String? description,
    bool? isActive,
    File? image,
    bool? batchTracked,
    bool? quickAdd,
    int? sortOrder,
    String? kind,
    String? mxikCode,
    String? packageCode,
    num? vatRate,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{r'toggle_active': toggleActive};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    final _data = FormData();
    _data.fields.add(MapEntry('name', name));
    _data.fields.add(MapEntry('category_id', categoryId.toString()));
    _data.fields.add(MapEntry('variants', variants));
    if (description != null) {
      _data.fields.add(MapEntry('description', description));
    }
    if (isActive != null) {
      _data.fields.add(MapEntry('is_active', isActive.toString()));
    }
    if (image != null) {
      _data.files.add(
        MapEntry(
          'image',
          MultipartFile.fromFileSync(
            image.path,
            filename: image.path.split(Platform.pathSeparator).last,
          ),
        ),
      );
    }
    if (batchTracked != null) {
      _data.fields.add(MapEntry('batch_tracked', batchTracked.toString()));
    }
    if (quickAdd != null) {
      _data.fields.add(MapEntry('quick_add', quickAdd.toString()));
    }
    if (sortOrder != null) {
      _data.fields.add(MapEntry('sort_order', sortOrder.toString()));
    }
    if (kind != null) {
      _data.fields.add(MapEntry('kind', kind));
    }
    if (mxikCode != null) {
      _data.fields.add(MapEntry('mxik_code', mxikCode));
    }
    if (packageCode != null) {
      _data.fields.add(MapEntry('package_code', packageCode));
    }
    if (vatRate != null) {
      _data.fields.add(MapEntry('vat_rate', vatRate.toString()));
    }
    final _options = _setStreamType<dynamic>(
      Options(
            method: 'POST',
            headers: _headers,
            extra: _extra,
            contentType: 'multipart/form-data',
          )
          .compose(
            _dio.options,
            '/v1/owner/products/${id}',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch(_options);
    final _value = _result.data;
    return _value;
  }

  @override
  Future<DeleteV1OwnerProductsIdResponse> ownerCatalogDeleteProduct({
    required String id,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<DeleteV1OwnerProductsIdResponse>(
      Options(method: 'DELETE', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/products/${id}',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late DeleteV1OwnerProductsIdResponse _value;
    try {
      _value = DeleteV1OwnerProductsIdResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<PatchV1OwnerProductsIdFiscalResponse> ownerCatalogUpdateFiscal({
    required String id,
    V1OwnerProductsIdFiscalRequestBody? body,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    queryParameters.removeWhere((k, v) => v == null);
    final _headers = <String, dynamic>{};
    final _data = <String, dynamic>{};
    _data.addAll(body?.toJson() ?? <String, dynamic>{});
    final _options = _setStreamType<PatchV1OwnerProductsIdFiscalResponse>(
      Options(method: 'PATCH', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/products/${id}/fiscal',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late PatchV1OwnerProductsIdFiscalResponse _value;
    try {
      _value = PatchV1OwnerProductsIdFiscalResponse.fromJson(_result.data!);
    } on Object catch (e, s) {
      errorLogger?.logError(e, s, _options, response: _result);
      rethrow;
    }
    return _value;
  }

  @override
  Future<DeleteV1OwnerProductsIdImageResponse> ownerCatalogDeleteImage({
    required String id,
  }) async {
    final _extra = <String, dynamic>{};
    final queryParameters = <String, dynamic>{};
    final _headers = <String, dynamic>{};
    const Map<String, dynamic>? _data = null;
    final _options = _setStreamType<DeleteV1OwnerProductsIdImageResponse>(
      Options(method: 'DELETE', headers: _headers, extra: _extra)
          .compose(
            _dio.options,
            '/v1/owner/products/${id}/image',
            queryParameters: queryParameters,
            data: _data,
          )
          .copyWith(baseUrl: _combineBaseUrls(_dio.options.baseUrl, baseUrl)),
    );
    final _result = await _dio.fetch<Map<String, Object?>>(_options);
    late DeleteV1OwnerProductsIdImageResponse _value;
    try {
      _value = DeleteV1OwnerProductsIdImageResponse.fromJson(_result.data!);
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
