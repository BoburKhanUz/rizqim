// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders _$HoldersFromJson(Map<String, dynamic> json) => Holders(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff3.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$HoldersToJson(Holders instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
