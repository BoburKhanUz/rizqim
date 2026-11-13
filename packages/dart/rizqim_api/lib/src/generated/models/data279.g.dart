// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data279.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data279 _$Data279FromJson(Map<String, dynamic> json) => Data279(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries5.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary8.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data279ToJson(Data279 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
