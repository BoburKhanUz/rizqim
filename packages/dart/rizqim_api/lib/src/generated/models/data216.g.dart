// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data216.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data216 _$Data216FromJson(Map<String, dynamic> json) => Data216(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries3.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary4.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data216ToJson(Data216 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'entries': instance.entries,
  'summary': instance.summary,
};
