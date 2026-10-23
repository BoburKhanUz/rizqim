// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'applied.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Applied _$AppliedFromJson(Map<String, dynamic> json) => Applied(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$AppliedToJson(Applied instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
};
