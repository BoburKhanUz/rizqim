// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel6.dart';
import 'branch14.dart';
import 'category.dart';
import 'products.dart';
import 'availability.dart';

part 'data209.g.dart';

@JsonSerializable()
class Data209 {
  const Data209({
    required this.channel,
    required this.menuVersion,
    required this.branch,
    required this.categories,
    required this.products,
    required this.availability,
  });

  factory Data209.fromJson(Map<String, Object?> json) =>
      _$Data209FromJson(json);

  final Channel6 channel;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Branch14 branch;
  final List<Category> categories;
  final List<Products> products;
  final Availability availability;

  Map<String, Object?> toJson() => _$Data209ToJson(this);
}
