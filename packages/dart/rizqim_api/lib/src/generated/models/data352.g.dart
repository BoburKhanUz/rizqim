// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data352.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data352 _$Data352FromJson(Map<String, dynamic> json) => Data352(
  branchId: (json['branch_id'] as num).toInt(),
  date: json['date'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries12.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary13.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data352ToJson(Data352 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'date': instance.date,
  'entries': instance.entries,
  'summary': instance.summary,
};
