// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries2.dart';

part 'data218.g.dart';

@JsonSerializable()
class Data218 {
  const Data218({required this.branchId, required this.entries});

  factory Data218.fromJson(Map<String, Object?> json) =>
      _$Data218FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final List<Entries2> entries;

  Map<String, Object?> toJson() => _$Data218ToJson(this);
}
