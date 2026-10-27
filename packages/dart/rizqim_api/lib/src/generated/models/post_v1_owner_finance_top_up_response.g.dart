// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_finance_top_up_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerFinanceTopUpResponse _$PostV1OwnerFinanceTopUpResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerFinanceTopUpResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data81.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerFinanceTopUpResponseToJson(
  PostV1OwnerFinanceTopUpResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
