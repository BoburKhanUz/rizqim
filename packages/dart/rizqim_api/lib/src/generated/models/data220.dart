// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots7.dart';
import 'halls5.dart';

part 'data220.g.dart';

@JsonSerializable()
class Data220 {
  const Data220({
    required this.branchId,
    required this.from,
    required this.to,
    required this.timezone,
    required this.slots,
    required this.halls,
  });

  factory Data220.fromJson(Map<String, Object?> json) =>
      _$Data220FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final String timezone;
  final Slots7 slots;
  final List<Halls5> halls;

  Map<String, Object?> toJson() => _$Data220ToJson(this);
}
