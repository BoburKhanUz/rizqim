// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_products_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerProductsResponse _$GetV1OwnerProductsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerProductsResponse(
  success: json['success'] as bool,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerProductsResponseToJson(
  GetV1OwnerProductsResponse instance,
) => <String, dynamic>{'success': instance.success, 'data': instance.data};
