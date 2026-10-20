// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_finance_top_up_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerFinanceTopUpRequestBody _$V1OwnerFinanceTopUpRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerFinanceTopUpRequestBody(
  amount: json['amount'] as num,
  provider: Provider.fromJson(json['provider'] as String),
);

Map<String, dynamic> _$V1OwnerFinanceTopUpRequestBodyToJson(
  V1OwnerFinanceTopUpRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'provider': instance.provider,
};
