// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data232.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data232 _$Data232FromJson(Map<String, dynamic> json) => Data232(
  contractNo: json['contract_no'] as String?,
  text: json['text'] as String,
  lines: (json['lines'] as List<dynamic>).map((e) => e as String).toList(),
  fields: Map<String, String>.from(json['fields'] as Map),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data232ToJson(Data232 instance) => <String, dynamic>{
  'contract_no': instance.contractNo,
  'text': instance.text,
  'lines': instance.lines,
  'fields': instance.fields,
  'warnings': instance.warnings,
};
