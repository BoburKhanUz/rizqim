// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_orders_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosOrdersResponse _$GetV1PosOrdersResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosOrdersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data171.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1PosOrdersResponseToJson(
  GetV1PosOrdersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
