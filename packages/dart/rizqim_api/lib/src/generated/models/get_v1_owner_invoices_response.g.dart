// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_invoices_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerInvoicesResponse _$GetV1OwnerInvoicesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerInvoicesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data75.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerInvoicesResponseToJson(
  GetV1OwnerInvoicesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
