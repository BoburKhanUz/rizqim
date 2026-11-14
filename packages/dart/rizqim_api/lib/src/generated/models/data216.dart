// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'entries3.dart';
import 'summary4.dart';

part 'data216.g.dart';

@JsonSerializable()
class Data216 {
  const Data216({
    required this.branchId,
    required this.from,
    required this.to,
    required this.entries,
    required this.summary,
  });

  factory Data216.fromJson(Map<String, Object?> json) =>
      _$Data216FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final List<Entries3> entries;
  final Summary4 summary;

  Map<String, Object?> toJson() => _$Data216ToJson(this);
}
