// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'corrections.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Corrections _$CorrectionsFromJson(Map<String, dynamic> json) => Corrections(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$CorrectionsToJson(Corrections instance) =>
    <String, dynamic>{'count': instance.count, 'amount': instance.amount};
