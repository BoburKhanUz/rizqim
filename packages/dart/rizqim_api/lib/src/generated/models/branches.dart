// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold_out.dart';
import 'batches.dart';

part 'branches.g.dart';

@JsonSerializable()
class Branches {
  const Branches({
    required this.branchId,
    required this.stopped,
    required this.soldOut,
    required this.stopList,
    required this.nextBatches,
    required this.batches,
  });

  factory Branches.fromJson(Map<String, Object?> json) =>
      _$BranchesFromJson(json);

  @JsonKey(name: 'branch_id')
  final String branchId;
  final List<dynamic> stopped;
  @JsonKey(name: 'sold_out')
  final SoldOut soldOut;
  @JsonKey(name: 'stop_list')
  final List<dynamic> stopList;
  @JsonKey(name: 'next_batches')
  final dynamic nextBatches;
  final List<Batches> batches;

  Map<String, Object?> toJson() => _$BranchesToJson(this);
}
