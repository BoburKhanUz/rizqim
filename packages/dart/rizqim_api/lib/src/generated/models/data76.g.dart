// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data76.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data76 _$Data76FromJson(Map<String, dynamic> json) => Data76(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data76ToJson(Data76 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
