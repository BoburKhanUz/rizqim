// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'batches3.dart';

part 'data35.g.dart';

@JsonSerializable()
class Data35 {
  const Data35({required this.branchId, required this.batches});

  factory Data35.fromJson(Map<String, Object?> json) => _$Data35FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Batches3> batches;

  Map<String, Object?> toJson() => _$Data35ToJson(this);
}
