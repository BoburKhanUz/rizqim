// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'flags.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Flags _$FlagsFromJson(Map<String, dynamic> json) => Flags(
  flag: json['flag'] as String,
  opId: json['op_id'] as String?,
  amount: json['amount'] as String?,
  name: json['name'] as String?,
);

Map<String, dynamic> _$FlagsToJson(Flags instance) => <String, dynamic>{
  'flag': instance.flag,
  'op_id': instance.opId,
  'amount': instance.amount,
  'name': instance.name,
};
