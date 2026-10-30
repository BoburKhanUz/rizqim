// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data271.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data271 _$Data271FromJson(Map<String, dynamic> json) => Data271(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries5.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary8.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data271ToJson(Data271 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
