// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_catalog_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCatalogResponse _$GetV1PosCatalogResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosCatalogResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data140.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosCatalogResponseToJson(
  GetV1PosCatalogResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
