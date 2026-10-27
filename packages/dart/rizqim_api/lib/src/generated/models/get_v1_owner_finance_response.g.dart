// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_finance_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFinanceResponse _$GetV1OwnerFinanceResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerFinanceResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data78.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerFinanceResponseToJson(
  GetV1OwnerFinanceResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
