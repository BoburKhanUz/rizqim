// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data165.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data165 _$Data165FromJson(Map<String, dynamic> json) => Data165(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary3.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data165ToJson(Data165 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'entries': instance.entries,
  'summary': instance.summary,
};
