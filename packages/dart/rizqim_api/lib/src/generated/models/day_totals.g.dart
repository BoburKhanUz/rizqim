// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'day_totals.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DayTotals _$DayTotalsFromJson(Map<String, dynamic> json) => DayTotals(
  date: json['date'] as String,
  amount: json['amount'] as String,
  meals: (json['meals'] as num).toInt(),
);

Map<String, dynamic> _$DayTotalsToJson(DayTotals instance) => <String, dynamic>{
  'date': instance.date,
  'amount': instance.amount,
  'meals': instance.meals,
};
