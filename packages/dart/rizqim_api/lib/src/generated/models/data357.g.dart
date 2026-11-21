// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data357.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data357 _$Data357FromJson(Map<String, dynamic> json) => Data357(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries13.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary14.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data357ToJson(Data357 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
