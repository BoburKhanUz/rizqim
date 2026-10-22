// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'availability.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Availability _$AvailabilityFromJson(Map<String, dynamic> json) => Availability(
  branchId: (json['branch_id'] as num).toInt(),
  stopped: Stopped.fromJson(json['stopped'] as Map<String, dynamic>),
  soldOut: SoldOut2.fromJson(json['sold_out'] as Map<String, dynamic>),
  stopList: (json['stop_list'] as List<dynamic>)
      .map((e) => StopList.fromJson(e as Map<String, dynamic>))
      .toList(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$AvailabilityToJson(Availability instance) =>
    <String, dynamic>{
      'branch_id': instance.branchId,
      'stopped': instance.stopped,
      'sold_out': instance.soldOut,
      'stop_list': instance.stopList,
      'batches': instance.batches,
    };
