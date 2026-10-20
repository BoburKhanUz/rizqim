// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_payouts_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPayoutsRequestBody _$V1OwnerPayoutsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerPayoutsRequestBody(
  amount: json['amount'] as num,
  cardNumber: json['card_number'] as String,
  notes: json['notes'] as String?,
);

Map<String, dynamic> _$V1OwnerPayoutsRequestBodyToJson(
  V1OwnerPayoutsRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'card_number': instance.cardNumber,
  'notes': instance.notes,
};
