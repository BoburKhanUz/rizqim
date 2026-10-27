// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_staff_orders_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1StaffOrdersResponse _$GetV1StaffOrdersResponseFromJson(
  Map<String, dynamic> json,
) => GetV1StaffOrdersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data184.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta4.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1StaffOrdersResponseToJson(
  GetV1StaffOrdersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
  'meta': instance.meta,
};
