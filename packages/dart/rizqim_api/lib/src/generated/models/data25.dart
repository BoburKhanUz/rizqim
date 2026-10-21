// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'batches2.dart';

part 'data25.g.dart';

@JsonSerializable()
class Data25 {
  const Data25({required this.branchId, required this.batches});

  factory Data25.fromJson(Map<String, Object?> json) => _$Data25FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Batches2> batches;

  Map<String, Object?> toJson() => _$Data25ToJson(this);
}
