// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries2.dart';

part 'data220.g.dart';

@JsonSerializable()
class Data220 {
  const Data220({required this.branchId, required this.entries});

  factory Data220.fromJson(Map<String, Object?> json) =>
      _$Data220FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Entries2> entries;

  Map<String, Object?> toJson() => _$Data220ToJson(this);
}
