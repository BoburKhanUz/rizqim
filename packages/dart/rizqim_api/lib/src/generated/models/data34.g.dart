// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data34.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data34 _$Data34FromJson(Map<String, dynamic> json) => Data34(
  branchId: (json['branch_id'] as num).toInt(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data34ToJson(Data34 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'batches': instance.batches,
};
