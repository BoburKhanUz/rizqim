// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_delivery_quote_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1DeliveryQuoteResponse _$GetV1DeliveryQuoteResponseFromJson(
  Map<String, dynamic> json,
) => GetV1DeliveryQuoteResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data29.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1DeliveryQuoteResponseToJson(
  GetV1DeliveryQuoteResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
