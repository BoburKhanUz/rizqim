// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_finance_wallet_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFinanceWalletResponse _$GetV1OwnerFinanceWalletResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerFinanceWalletResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data63.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerFinanceWalletResponseToJson(
  GetV1OwnerFinanceWalletResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
