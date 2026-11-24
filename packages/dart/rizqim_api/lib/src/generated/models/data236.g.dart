// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data236.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data236 _$Data236FromJson(Map<String, dynamic> json) => Data236(
  contractNo: json['contract_no'] as String?,
  text: json['text'] as String,
  lines: (json['lines'] as List<dynamic>).map((e) => e as String).toList(),
  fields: Map<String, String>.from(json['fields'] as Map),
  warnings: (json['warnings'] as List<dynamic>)
      .map((e) => Warnings14.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data236ToJson(Data236 instance) => <String, dynamic>{
  'contract_no': instance.contractNo,
  'text': instance.text,
  'lines': instance.lines,
  'fields': instance.fields,
  'warnings': instance.warnings,
};
