// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data301.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data301 _$Data301FromJson(Map<String, dynamic> json) => Data301(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries7.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary8.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data301ToJson(Data301 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
