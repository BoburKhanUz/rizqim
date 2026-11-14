// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_catalog_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsCatalogResponse _$GetV1PosBanquetsCatalogResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBanquetsCatalogResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data221.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBanquetsCatalogResponseToJson(
  GetV1PosBanquetsCatalogResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
