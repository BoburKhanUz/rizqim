// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account.dart';
import 'entries3.dart';

part 'data216.g.dart';

@JsonSerializable()
class Data216 {
  const Data216({required this.account, required this.entries});

  factory Data216.fromJson(Map<String, Object?> json) =>
      _$Data216FromJson(json);

  final Account account;
  final List<Entries3> entries;

  Map<String, Object?> toJson() => _$Data216ToJson(this);
}
