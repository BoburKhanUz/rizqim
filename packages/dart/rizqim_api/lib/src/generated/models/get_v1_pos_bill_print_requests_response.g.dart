// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_bill_print_requests_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBillPrintRequestsResponse _$GetV1PosBillPrintRequestsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosBillPrintRequestsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data245.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosBillPrintRequestsResponseToJson(
  GetV1PosBillPrintRequestsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
