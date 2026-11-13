// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows3 _$Rows3FromJson(Map<String, dynamic> json) => Rows3(
  member: json['member'] == null
      ? null
      : Member3.fromJson(json['member'] as Map<String, dynamic>),
  cells: (json['cells'] as List<dynamic>)
      .map((e) => Cells.fromJson(e as Map<String, dynamic>))
      .toList(),
  amount: json['amount'] as String,
  meals: (json['meals'] as num).toInt(),
);

Map<String, dynamic> _$Rows3ToJson(Rows3 instance) => <String, dynamic>{
  'member': instance.member,
  'cells': instance.cells,
  'amount': instance.amount,
  'meals': instance.meals,
};
