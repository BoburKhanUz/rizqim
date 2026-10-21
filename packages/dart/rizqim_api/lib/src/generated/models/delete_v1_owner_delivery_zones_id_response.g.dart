// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_delivery_zones_id_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerDeliveryZonesIdResponse
_$DeleteV1OwnerDeliveryZonesIdResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerDeliveryZonesIdResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'],
    );

Map<String, dynamic> _$DeleteV1OwnerDeliveryZonesIdResponseToJson(
  DeleteV1OwnerDeliveryZonesIdResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
