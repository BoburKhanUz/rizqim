// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cells.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Cells _$CellsFromJson(Map<String, dynamic> json) => Cells(
  date: json['date'] as String,
  amount: json['amount'] as String,
  meals: (json['meals'] as num).toInt(),
);

Map<String, dynamic> _$CellsToJson(Cells instance) => <String, dynamic>{
  'date': instance.date,
  'amount': instance.amount,
  'meals': instance.meals,
};
