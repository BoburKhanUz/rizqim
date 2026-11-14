// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows15 _$Rows15FromJson(Map<String, dynamic> json) => Rows15(
  member: json['member'] == null
      ? null
      : Member3.fromJson(json['member'] as Map<String, dynamic>),
  cells: (json['cells'] as List<dynamic>)
      .map((e) => Cells.fromJson(e as Map<String, dynamic>))
      .toList(),
  amount: json['amount'] as String,
  meals: (json['meals'] as num).toInt(),
);

Map<String, dynamic> _$Rows15ToJson(Rows15 instance) => <String, dynamic>{
  'member': instance.member,
  'cells': instance.cells,
  'amount': instance.amount,
  'meals': instance.meals,
};
