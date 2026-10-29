// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_reports_top_products_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerReportsTopProductsResponse
_$GetV1OwnerReportsTopProductsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerReportsTopProductsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data131.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerReportsTopProductsResponseToJson(
  GetV1OwnerReportsTopProductsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
