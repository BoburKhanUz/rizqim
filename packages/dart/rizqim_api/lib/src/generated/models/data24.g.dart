// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data24 _$Data24FromJson(Map<String, dynamic> json) => Data24(
  branchId: (json['branch_id'] as num).toInt(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data24ToJson(Data24 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'batches': instance.batches,
};
