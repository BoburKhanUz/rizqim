// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data235.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data235 _$Data235FromJson(Map<String, dynamic> json) => Data235(
  contractNo: json['contract_no'] as String?,
  text: json['text'] as String,
  lines: (json['lines'] as List<dynamic>).map((e) => e as String).toList(),
  fields: Map<String, String>.from(json['fields'] as Map),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings13.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data235ToJson(Data235 instance) => <String, dynamic>{
  'contract_no': instance.contractNo,
  'text': instance.text,
  'lines': instance.lines,
  'fields': instance.fields,
  'warnings': instance.warnings,
};
