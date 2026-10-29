// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'stopped.dart';
import 'sold_out2.dart';
import 'stop_list.dart';
import 'batches4.dart';

part 'availability.g.dart';

@JsonSerializable()
class Availability {
  const Availability({
    required this.branchId,
    required this.stopped,
    required this.soldOut,
    required this.stopList,
    required this.batches,
  });

  factory Availability.fromJson(Map<String, Object?> json) =>
      _$AvailabilityFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final Stopped stopped;
  @JsonKey(name: 'sold_out')
  final SoldOut2 soldOut;
  @JsonKey(name: 'stop_list')
  final List<StopList> stopList;
  final List<Batches4> batches;

  Map<String, Object?> toJson() => _$AvailabilityToJson(this);
}
