// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_auto_guest_items_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerAutoGuestItemsResponse _$GetV1OwnerAutoGuestItemsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerAutoGuestItemsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data48.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1OwnerAutoGuestItemsResponseToJson(
  GetV1OwnerAutoGuestItemsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
