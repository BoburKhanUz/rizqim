// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_delivery_zones_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerDeliveryZonesResponse _$GetV1OwnerDeliveryZonesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerDeliveryZonesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data63.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerDeliveryZonesResponseToJson(
  GetV1OwnerDeliveryZonesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
