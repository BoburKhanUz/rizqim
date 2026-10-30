// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data251.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data251 _$Data251FromJson(Map<String, dynamic> json) => Data251(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries5.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary7.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data251ToJson(Data251 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
