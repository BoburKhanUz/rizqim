// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data78.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data78 _$Data78FromJson(Map<String, dynamic> json) => Data78(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data78ToJson(Data78 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
