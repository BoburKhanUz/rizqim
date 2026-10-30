// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries2.dart';
import 'summary3.dart';

part 'data165.g.dart';

@JsonSerializable()
class Data165 {
  const Data165({
    required this.branchId,
    required this.from,
    required this.to,
    required this.entries,
    required this.summary,
  });

  factory Data165.fromJson(Map<String, Object?> json) =>
      _$Data165FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final List<Entries2> entries;
  final Summary3 summary;

  Map<String, Object?> toJson() => _$Data165ToJson(this);
}
