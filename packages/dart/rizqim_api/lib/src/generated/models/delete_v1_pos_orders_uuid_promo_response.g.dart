// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_pos_orders_uuid_promo_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1PosOrdersUuidPromoResponse _$DeleteV1PosOrdersUuidPromoResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1PosOrdersUuidPromoResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data269.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DeleteV1PosOrdersUuidPromoResponseToJson(
  DeleteV1PosOrdersUuidPromoResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
