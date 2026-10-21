// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_staff_kitchen_tickets_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1StaffKitchenTicketsResponse _$GetV1StaffKitchenTicketsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1StaffKitchenTicketsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data80.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1StaffKitchenTicketsResponseToJson(
  GetV1StaffKitchenTicketsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
  'meta': instance.meta,
};
